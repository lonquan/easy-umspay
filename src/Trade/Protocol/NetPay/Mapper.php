<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol\NetPay;

use DateTimeInterface;
use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Support\Times;

use function array_diff_key;
use function array_flip;
use function array_replace;
use function is_scalar;

/**
 * 老购物车报文映射
 *
 * 老购物车报文统一使用 mid/tid/merOrderId 与 requestTimestamp；
 * 预授权相关接口字段命名不同（reqTs/mchntNo/termNo），在二期单独映射。
 */
final class Mapper
{
    /**
     * @var list<string>
     */
    private const array PAY_INPUT_KEYS = [
        'orderNo',
        'amount',
        'description',
        'notifyUrl',
        'payerId',
        'subAppId',
        'expireAt',
        'returnUrl',
        'attachedData',
        'instMid',
        'scene',
    ];

    /**
     * @var list<string>
     */
    private const array QUERY_INPUT_KEYS = ['orderNo', 'scene'];

    /**
     * @var list<string>
     */
    private const array CLOSE_INPUT_KEYS = ['orderNo', 'scene'];

    /**
     * @var list<string>
     */
    private const array REFUND_INPUT_KEYS = ['orderNo', 'refundOrderNo', 'amount', 'scene'];

    /**
     * @var list<string>
     */
    private const array REFUND_QUERY_INPUT_KEYS = ['refundOrderNo', 'scene'];

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function pay(array $order, PayScene $scene, array $config): array
    {
        $payload = self::base($config);
        $payload['requestTimestamp'] = Times::now();
        $payload['merOrderId'] = (string) ($order['orderNo'] ?? '');
        $payload['totalAmount'] = (string) ($order['amount'] ?? '');
        $payload['orderDesc'] = (string) ($order['description'] ?? '');
        $payload['notifyUrl'] = (string) ($order['notifyUrl'] ?? '');
        $payload['msgId'] = (string) ($order['orderNo'] ?? '');
        $payload['srcReserve'] = (string) ($order['orderNo'] ?? '');
        $payload['instMid'] = self::instMid($scene, $config, self::optionalString($order, 'instMid'));

        $tradeType = self::tradeType($scene);

        if ($tradeType !== null) {
            $payload['tradeType'] = $tradeType;
        }

        $payerId = self::optionalString($order, 'payerId');

        if ($payerId !== null) {
            $payload[$scene->channel()->payerField()] = $payerId;
        }

        $subAppId = self::optionalString($order, 'subAppId');

        if ($subAppId !== null) {
            $payload['subAppId'] = $subAppId;
        }

        $returnUrl = self::optionalString($order, 'returnUrl');

        if ($returnUrl !== null) {
            $payload['returnUrl'] = $returnUrl;
        }

        $expireAt = $order['expireAt'] ?? null;

        if ($expireAt instanceof DateTimeInterface) {
            $payload['expireTime'] = Times::format($expireAt);
        }

        $attachedData = self::optionalString($order, 'attachedData');

        if ($attachedData !== null) {
            $payload['attachedData'] = $attachedData;
        }

        $msgSrc = (string) ($config['message_source'] ?? '');

        if ($msgSrc !== '') {
            $payload['msgSrc'] = $msgSrc;
        }

        return self::mergeExtra($payload, $order, self::PAY_INPUT_KEYS);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function query(array $order, PayScene $scene, array $config): array
    {
        $payload = self::base($config);
        $payload['requestTimestamp'] = Times::now();
        $payload['merOrderId'] = (string) ($order['orderNo'] ?? '');
        $payload['instMid'] = self::instMid($scene, $config, self::optionalString($order, 'instMid'));

        return self::mergeExtra($payload, $order, self::QUERY_INPUT_KEYS);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    public static function refund(array $refund, PayScene $scene, array $config): array
    {
        $payload = self::base($config);
        $payload['requestTimestamp'] = Times::now();
        $payload['merOrderId'] = (string) ($refund['orderNo'] ?? '');
        $payload['refundOrderId'] = (string) ($refund['refundOrderNo'] ?? '');
        $payload['refundAmount'] = (string) ($refund['amount'] ?? '');
        $payload['instMid'] = self::instMid($scene, $config, self::optionalString($refund, 'instMid'));

        return self::mergeExtra($payload, $refund, self::REFUND_INPUT_KEYS);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    public static function refundQuery(array $refund, PayScene $scene, array $config): array
    {
        $payload = self::base($config);
        $payload['requestTimestamp'] = Times::now();
        $payload['merOrderId'] = (string) ($refund['refundOrderNo'] ?? '');
        $payload['instMid'] = self::instMid($scene, $config, self::optionalString($refund, 'instMid'));

        return self::mergeExtra($payload, $refund, self::REFUND_QUERY_INPUT_KEYS);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function close(array $order, PayScene $scene, array $config): array
    {
        $payload = self::base($config);
        $payload['requestTimestamp'] = Times::now();
        $payload['merOrderId'] = (string) ($order['orderNo'] ?? '');
        $payload['instMid'] = self::instMid($scene, $config, self::optionalString($order, 'instMid'));

        return self::mergeExtra($payload, $order, self::CLOSE_INPUT_KEYS);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function base(array $config): array
    {
        return [
            'mid' => (string) ($config['merchant_no'] ?? ''),
            'tid' => (string) ($config['terminal_no'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function instMid(PayScene $scene, array $config, ?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        $configured = (string) ($config['inst_mid'] ?? '');

        return $configured !== '' ? $configured : $scene->defaultInstMid();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @param  list<string>  $knownKeys
     * @return array<string, mixed>
     */
    private static function mergeExtra(array $payload, array $data, array $knownKeys): array
    {
        $extra = array_diff_key($data, array_flip($knownKeys));

        return array_replace($payload, $extra);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function tradeType(PayScene $scene): ?string
    {
        return match (true) {
            $scene === PayScene::UnionPayWxMiniApp => 'UP_WX_MINI',
            $scene->isMiniApp() => 'MINI',
            $scene->isOfficialAccount() => 'JSAPI',
            $scene->isApp() => 'APP',
            default => null,
        };
    }
}
