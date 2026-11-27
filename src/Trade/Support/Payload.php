<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Support;

use DateTimeImmutable;
use DateTimeZone;
use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\RefundStatus;
use EasyUmsPay\Trade\Enums\TradeStatus;

use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function strtoupper;
use function trim;

/**
 * 渠道响应归一化。
 *
 * 两代购物车的字段别名、成功码与取值解析统一放在这里，输出固定键数组。
 * 本类只做只读取值，不做业务校验；参数缺失时返回 null / 空字符串。
 */
final class Payload
{
    /**
     * 渠道成功码（老购物车 errCode=S0000 之外还有 SUCCESS，新购物车为 respCode=0000）。
     */
    private const array SUCCESS_CODES = ['SUCCESS', '00', '0000'];

    /**
     * 错误码字段，按优先级取第一个非空值。
     */
    private const array ERROR_CODE_KEYS = ['errCode', 'err_code', 'respCode'];

    /**
     * 错误描述字段，按优先级取第一个非空值。
     */
    private const array ERROR_MESSAGE_KEYS = ['errInfo', 'errMsg', 'err_msg', 'respDesc', 'errmsg'];

    /**
     * 商户订单号字段：新购物车 mchntOrderId，老购物车 merOrderId。
     */
    private const array MERCHANT_ORDER_KEYS = ['mchntOrderId', 'merOrderId'];

    /**
     * 平台/渠道流水号字段：seqId 为主，pltSsn 为部分场景的等价字段。
     */
    private const array SEQUENCE_KEYS = ['seqId', 'pltSsn'];

    /**
     * 支付时间字段。
     */
    private const array PAID_AT_KEYS = ['payTime', 'payDate', 'pay_time'];

    private function __construct()
    {
    }

    /**
     * @param  array<string, mixed>  $raw  渠道原始响应报文
     * @return array{
     *   success:bool,
     *   orderNo:?string,
     *   seqId:?string,
     *   targetOrderId:?string,
     *   status:?TradeStatus,
     *   amount:?int,
     *   paidAt:?DateTimeImmutable,
     *   refundOrderNo:?string,
     *   refundTargetOrderId:?string,
     *   refundStatus:?RefundStatus,
     *   payParams:array<string,mixed>,
     *   errorCode:string,
     *   errorMessage:string,
     *   raw:array<string,mixed>
     * }
     */
    public static function normalize(
        array $raw,
        ?PayScene $payScene = null,
        bool $requireClosed = false,
    ): array {
        $accepted = self::isAccepted($raw);
        $status = self::statusOrNull($raw);
        $closed = $status === TradeStatus::TRADE_CLOSED;
        $success = $requireClosed ? ($accepted && $closed) : $accepted;
        $errorCode = $success ? '' : self::errorCode($raw);
        $errorMessage = $success ? '' : self::errorMessage($raw);

        if (! $success) {
            if ($requireClosed && $accepted) {
                $errorCode = 'CLOSE_UNCONFIRMED';

                if ($errorMessage === '') {
                    $errorMessage = '渠道未返回关闭结果';
                }
            } elseif ($errorCode === '') {
                $errorCode = 'CHANNEL_REJECTED';
            }
        }

        return [
            'success' => $success,
            'orderNo' => self::merchantOrderNo($raw),
            'seqId' => self::seqId($raw),
            'targetOrderId' => self::targetOrderId($raw),
            'status' => $status,
            'amount' => self::amount($raw),
            'paidAt' => self::paidAt($raw),
            'refundOrderNo' => self::refundOrderNo($raw),
            'refundTargetOrderId' => self::refundTargetOrderId($raw),
            'refundStatus' => self::refundStatus($raw),
            'payParams' => $payScene === null ? [] : self::payParams($raw, $payScene),
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
            'raw' => $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function string(array $raw, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function integer(array $raw, string $key): ?int
    {
        $value = $raw[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function errorCode(array $raw): string
    {
        return self::string($raw, ...self::ERROR_CODE_KEYS) ?? '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function errorMessage(array $raw): string
    {
        foreach (self::ERROR_MESSAGE_KEYS as $key) {
            $value = $raw[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function isAccepted(array $raw): bool
    {
        $code = self::errorCode($raw);

        return $code === '' || in_array(strtoupper($code), self::SUCCESS_CODES, true);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function statusOrNull(array $raw): ?TradeStatus
    {
        $value = self::string($raw, 'status');

        return $value === null ? null : TradeStatus::tryFrom($value);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function merchantOrderNo(array $raw): ?string
    {
        return self::string($raw, ...self::MERCHANT_ORDER_KEYS);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function seqId(array $raw): ?string
    {
        return self::string($raw, ...self::SEQUENCE_KEYS);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function targetOrderId(array $raw): ?string
    {
        return self::string($raw, 'targetOrderId');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function amount(array $raw): ?int
    {
        return self::integer($raw, 'totalAmount');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function paidAt(array $raw): ?DateTimeImmutable
    {
        $value = self::string($raw, ...self::PAID_AT_KEYS);

        if ($value === null) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('Asia/Shanghai'));

        return $time === false ? null : $time;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function refundStatus(array $raw): ?RefundStatus
    {
        $value = self::string($raw, 'refundStatus');

        if ($value === null) {
            return null;
        }

        return match (strtoupper($value)) {
            'SUCCESS', 'SUCESS', 'REFUND_SUCCESS' => RefundStatus::REFUND_SUCCESS,
            'FAIL', 'FAILED', 'REFUND_FAILED' => RefundStatus::REFUND_FAILED,
            'PROCESSING' => RefundStatus::PROCESSING,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function refundOrderNo(array $raw): ?string
    {
        return self::string($raw, 'refundOrderId');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function refundTargetOrderId(array $raw): ?string
    {
        return self::string($raw, 'refundTargetOrderId');
    }

    /**
     * 按支付场景提取调起支付参数。
     *
     * 小程序 / 公众号 / APP 分别读取 miniPayRequest、jsPayRequest、appPayRequest（数组或 JSON 字符串）；
     * H5 读取 preUrl + nextUrl 拼接地址。
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function payParams(array $raw, PayScene $scene): array
    {
        $keys = match (true) {
            $scene->isH5() => null,
            $scene->isMiniApp() => ['miniPayRequest'],
            $scene->isOfficialAccount() => ['jsPayRequest'],
            $scene->isApp() => ['appPayRequest'],
            default => [],
        };

        if ($keys === null) {
            return self::h5PayParams($raw);
        }

        foreach ($keys as $key) {
            $params = self::nestedParams($raw, $key);

            if ($params !== []) {
                return $params;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function nestedParams(array $raw, string $key): array
    {
        $value = $raw[$key] ?? null;

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function h5PayParams(array $raw): array
    {
        $pre = self::string($raw, 'preUrl');
        $next = self::string($raw, 'nextUrl');

        if ($pre !== null && $next !== null) {
            return ['payUrl' => $pre . $next];
        }

        foreach (['payUrl', 'h5PayUrl', 'url'] as $key) {
            $value = self::string($raw, $key);

            if ($value !== null) {
                return ['payUrl' => $value];
            }
        }

        return [];
    }
}
