<?php

declare(strict_types=1);

namespace EasyUmsPay\Crypto;

readonly class HmacSha256Signer implements SignerInterface
{
    public function __construct(protected string $secretKey)
    {

    }

    /**
     * 对数据进行HMAC-SHA256签名
     *
     * @param  string  $data  待签名的数据
     * @return string Base64编码的签名
     */
    public function sign(string $data): string
    {
        $hash = hash_hmac('sha256', $data, $this->secretKey, true);

        return base64_encode($hash);
    }

    /**
     * 验证HMAC-SHA256签名
     *
     * @param  string  $data  原始数据
     * @param  string  $signature  待验证的签名（Base64编码）
     * @return bool 签名是否有效
     */
    public function verify(string $data, string $signature): bool
    {
        $expectedSignature = $this->sign($data);

        return hash_equals($expectedSignature, $signature);
    }
}
