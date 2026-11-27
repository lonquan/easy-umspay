<?php

declare(strict_types=1);

namespace EasyUmsPay\Crypto;

interface SignerInterface
{
    /**
     * 对数据进行签名
     *
     * @param  string  $data  待签名的数据
     * @return string 签名结果
     */
    public function sign(string $data): string;

    /**
     * 验证签名是否正确
     *
     * @param  string  $data  原始数据
     * @param  string  $signature  待验证的签名
     * @return bool 签名是否有效
     */
    public function verify(string $data, string $signature): bool;
}
