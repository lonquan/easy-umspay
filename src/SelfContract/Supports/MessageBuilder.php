<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Supports;

use Throwable;

/**
 * 请求报文组装器
 *
 * 自动添加必填字段: service, accesser_id, sign_type, request_date, request_seq
 */
final readonly class MessageBuilder
{
    public function __construct(protected RequestSequenceGenerator $sequenceGenerator, protected string $accesserId)
    {
    }

    /**
     * 构建完整的请求报文
     *
     * @param  string  $service  服务名称
     * @param  array<string, mixed>  $businessData  业务参数
     * @return array<string, mixed> 完整报文
     *
     * @throws Throwable
     */
    public function build(string $service, array $businessData): array
    {
        return [
            'service' => $service,
            'accesser_id' => $this->accesserId,
            'sign_type' => 'SHA-256',
            'request_date' => date('YmdHis'),
            'request_seq' => $this->sequenceGenerator->generate(),
            ...$businessData,
        ];
    }
}
