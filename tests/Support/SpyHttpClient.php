<?php

declare(strict_types=1);

namespace EasyUmsPay\Tests\Support;

use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class SpyHttpClient implements HttpClientInterface, LoggerAwareInterface
{
    public ?LoggerInterface $logger = null;

    public function __construct(protected ResponseInterface $response)
    {
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->response;
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new class implements ResponseStreamInterface
        {
            public function rewind(): void
            {
            }

            public function current(): ChunkInterface
            {
                throw new LogicException('Not implemented for tests.');
            }

            public function key(): ResponseInterface
            {
                throw new LogicException('Not implemented for tests.');
            }

            public function next(): void
            {
            }

            public function valid(): bool
            {
                return false;
            }
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        return $this;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
