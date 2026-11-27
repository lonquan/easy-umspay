<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

use function hash;
use function hash_equals;
use function implode;
use function is_string;
use function ksort;
use function strtoupper;

/**
 * 通知验签公共实现
 *
 * 官方规则（老购物车「通知验签规则」，两代购物车通知同构）：
 * 排除 sign 后按参数名 ASCII 升序，拼成 `k=v&k=v...`，末尾直接追加通讯密钥，
 * 取 SHA256 或 MD5 的大写十六进制与 sign 比较。
 *
 * 子类只在与官方规则不一致时才覆写 {@see signedString()}。
 */
abstract class AbstractSignature implements SignatureStrategy
{
    /**
     * @param  array<string, string>  $fields  通知表单字段（已 URL 解码）
     * @param  string  $key  通知通讯密钥
     * @param  string  $algorithm  hash 算法（sha256 / md5）
     */
    public function verify(array $fields, string $key, string $algorithm): bool
    {
        $sign = $fields['sign'] ?? null;

        if (! is_string($sign) || $sign === '' || $key === '') {
            return false;
        }
        $expected = strtoupper(hash($algorithm, $this->signedString($fields, $key)));

        return hash_equals($expected, strtoupper($sign));
    }

    /**
     * 待签名串
     *
     * @param  array<string, string>  $fields
     */
    protected function signedString(array $fields, string $key): string
    {
        unset($fields['sign']);

        ksort($fields, SORT_STRING);

        $pairs = [];

        foreach ($fields as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('&', $pairs) . $key;
    }
}
