<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

use EasyUmsPay\Trade\Enums\TradeProtocol;

/**
 * 通知验签入口
 *
 * 算法取通知报文的 signType（SHA256 与 MD5 都支持）；缺失或未知一律拒绝。
 */
final readonly class Verifier
{
    public function __construct(private SignatureStrategy $strategy)
    {
    }

    public static function forProtocol(TradeProtocol $protocol): self
    {
        return new self(match ($protocol) {
            TradeProtocol::NetPay => new NetPaySignature,
            TradeProtocol::ShoppingCart => new ShoppingCartSignature,
        });
    }

    /**
     * @param  array<string, string>  $fields  通知表单字段
     * @param  string  $key  通知通讯密钥
     */
    public function verify(array $fields, string $key): bool
    {
        $signType = NotifyFields::signType($fields);

        if ($signType === null) {
            return false;
        }

        $algorithm = match (strtoupper(str_replace(['-', '_'], '', $signType))) {
            'SHA256' => 'sha256',
            'MD5' => 'md5',
            default => null,
        };

        if ($algorithm === null) {
            return false;
        }

        return $this->strategy->verify($fields, $key, $algorithm);
    }
}
