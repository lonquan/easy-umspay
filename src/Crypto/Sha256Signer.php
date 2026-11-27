<?php

declare(strict_types=1);

namespace EasyUmsPay\Crypto;

class Sha256Signer implements SignerInterface
{
    /**
     * 对数据进行SHA-256签名
     *
     * @param  string  $data  待签名的数据
     * @return string 小写的SHA-256哈希值（十六进制）
     */
    public function sign(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * 验证SHA-256签名
     *
     * @param  string  $data  原始数据
     * @param  string  $signature  待验证的签名
     * @return bool 签名是否有效
     */
    public function verify(string $data, string $signature): bool
    {
        $expectedSignature = $this->sign($data);

        return hash_equals($expectedSignature, strtolower($signature));
    }
}
