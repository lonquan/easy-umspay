<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade;

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\TradeProtocol;
use EasyUmsPay\Trade\Protocol\ProtocolInterface;
use EasyUmsPay\Trade\Support\Payload;

use function is_string;

/**
 * 交易门面
 *
 * 请求参数使用单个数组，协议差异在构造时按配置选定。
 * 返回值由 {@see Payload::normalize()} 归一化为固定键数组。
 *
 * @phpstan-type NormalizedResult array{success:bool,orderNo:?string,seqId:?string,targetOrderId:?string,status:?\EasyUmsPay\Trade\Enums\TradeStatus,amount:?int,paidAt:?\DateTimeImmutable,refundOrderNo:?string,refundTargetOrderId:?string,refundStatus:?\EasyUmsPay\Trade\Enums\RefundStatus,payParams:array<string,mixed>,errorCode:string,errorMessage:string,raw:array<string,mixed>}
 */
final readonly class TradeClient
{
    public function __construct(private ProtocolInterface $protocol)
    {
    }

    public function protocol(): TradeProtocol
    {
        return $this->protocol->protocol();
    }

    /**
     * @param  array<string, mixed>  $order
     * @return NormalizedResult
     */
    public function pay(array $order, PayScene $scene): array
    {
        return $this->normalize(
            $this->protocol->pay($order, $scene),
            $scene,
            (string) ($order['orderNo'] ?? ''),
        );
    }

    /**
     * @param  string|array<string, mixed>  $order
     * @return NormalizedResult
     */
    public function query(string|array $order, PayScene $scene): array
    {
        $data = $this->normalizeInput($order, 'orderNo');

        return $this->normalize(
            $this->protocol->query($data, $scene),
            null,
            (string) ($data['orderNo'] ?? ''),
        );
    }

    /**
     * @param  string|array<string, mixed>  $order
     * @return NormalizedResult
     */
    public function close(string|array $order, PayScene $scene): array
    {
        $data = $this->normalizeInput($order, 'orderNo');

        return $this->normalize(
            $this->protocol->close($data, $scene),
            null,
            (string) ($data['orderNo'] ?? ''),
            requireClosed: true,
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return NormalizedResult
     */
    public function refund(array $refund, PayScene $scene): array
    {
        return $this->normalize(
            $this->protocol->refund($refund, $scene),
            null,
            refundOrderNo: (string) ($refund['refundOrderNo'] ?? ''),
        );
    }

    /**
     * @param  string|array<string, mixed>  $refund
     * @return NormalizedResult
     */
    public function refundQuery(string|array $refund, PayScene $scene): array
    {
        $data = $this->normalizeInput($refund, 'refundOrderNo');

        return $this->normalize(
            $this->protocol->refundQuery($data, $scene),
            refundOrderNo: (string) ($data['refundOrderNo'] ?? ''),
        );
    }

    /**
     * @param  string|array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(string|array $input, string $key): array
    {
        return is_string($input) ? [$key => $input] : $input;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return NormalizedResult
     */
    private function normalize(
        array $raw,
        ?PayScene $payScene = null,
        ?string $orderNo = null,
        bool $requireClosed = false,
        ?string $refundOrderNo = null,
    ): array {
        $result = Payload::normalize($raw, $payScene, $requireClosed);

        if ($orderNo !== null && $orderNo !== '') {
            $result['orderNo'] ??= $orderNo;
        }

        if ($refundOrderNo !== null && $refundOrderNo !== '') {
            $result['refundOrderNo'] ??= $refundOrderNo;
        }

        return $result;
    }
}
