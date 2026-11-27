<?php

declare(strict_types=1);

namespace EasyUmsPay\Http;

/**
 * HTTP 请求/响应日志开关。
 *
 * 默认全部关闭，按需开启。
 */
final readonly class HttpLogging
{
    public function __construct(
        public bool $request = false,
        public bool $response = false,
        public bool $body = false,
    ) {
    }
}
