<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

/**
 * 通知验签策略（不同协议代际的拼接规则不同）
 */
interface SignatureStrategy
{
    /**
     * @param  array<string, string>  $fields  已解码的通知字段（含 sign）
     * @param  string  $key  通知通讯密钥
     * @param  string  $algorithm  归一化后的算法标识（sha256 / md5）
     */
    public function verify(array $fields, string $key, string $algorithm): bool;
}
