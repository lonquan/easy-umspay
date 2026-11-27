<?php

declare(strict_types=1);

namespace EasyUmsPay\Tests\Support;

use Symfony\Contracts\HttpClient\ResponseInterface;

class SpyResponse implements ResponseInterface
{
    public int $getStatusCodeCalls = 0;

    public int $getHeadersCalls = 0;

    public int $getContentCalls = 0;

    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        protected string $content,
        protected array $headers = ['content-type' => ['application/json']],
        protected int $statusCode = 200,
    ) {
    }

    public function getStatusCode(): int
    {
        $this->getStatusCodeCalls++;

        return $this->statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->getHeadersCalls++;

        return $this->headers;
    }

    public function getContent(bool $throw = true): string
    {
        $this->getContentCalls++;

        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->content, true) ?? [];

        return $decoded;
    }

    public function cancel(): void
    {
    }

    public function getInfo(?string $type = null): mixed
    {
        return null;
    }
}
