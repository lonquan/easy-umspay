<?php

declare(strict_types=1);

namespace EasyUmsPay\Exceptions;

use Exception;
use Throwable;

class UmsException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public string $errorCode = '',
        public array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * 获取错误码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 获取上下文信息
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
