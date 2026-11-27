<?php

declare(strict_types=1);

namespace EasyUmsPay\Auth;

use Random\RandomException;

use function base64_encode;
use function bin2hex;
use function date;
use function hash;
use function hash_hmac;
use function random_bytes;
use function sprintf;

/**
 * OPEN-BODY-SIG 认证
 *
 * 新老购物车共用同一套认证，官方规范：
 * `Authorization: OPEN-BODY-SIG AppId="...",Timestamp="yyyyMMddHHmmss",Nonce="...",Signature="..."`
 * 其中 `Signature = Base64(HmacSHA256(appId + timestamp + nonce + SHA256_HEX_LOWER(body), appKey))`。
 *
 * 待签名的 body 必须是最终发送的 JSON 字符串，任何字段改动都要重新签名。
 */
final readonly class OpenBodySig
{
    public function __construct(
        private string $appId,
        private string $appKey,
    ) {
    }

    /**
     * 生成请求头 `Authorization` 的值
     *
     * @param  string  $body  最终发送的 JSON 报文体
     * @param  string|null  $timestamp  14 位时间戳，缺省取当前时间
     * @param  string|null  $nonce  随机串（≤128），缺省随机生成
     *
     * @throws RandomException
     */
    public function header(string $body, ?string $timestamp = null, ?string $nonce = null): string
    {
        $timestamp ??= date('YmdHis');
        $nonce ??= bin2hex(random_bytes(16));

        return sprintf(
            'OPEN-BODY-SIG AppId="%s",Timestamp="%s",Nonce="%s",Signature="%s"',
            $this->appId,
            $timestamp,
            $nonce,
            $this->signature($body, $timestamp, $nonce),
        );
    }

    /**
     * 生成签名内容（Base64）
     */
    public function signature(string $body, string $timestamp, string $nonce): string
    {
        $plain = $this->appId . $timestamp . $nonce . hash('sha256', $body);

        return base64_encode(hash_hmac('sha256', $plain, $this->appKey, true));
    }
}
