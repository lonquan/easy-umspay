<?php

declare(strict_types=1);

namespace EasyUmsPay\Exceptions;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class RequestException extends UmsException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public ?ResponseInterface $response = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, '', $context, $previous);
    }

    /**
     * 获取HTTP响应对象
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
