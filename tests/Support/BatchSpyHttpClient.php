<?php

declare(strict_types=1);

namespace EasyUmsPay\Tests\Support;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Throwable;

class BatchSpyHttpClient implements HttpClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $requestOptions = [];

    private int $requestIndex = 0;

    /**
     * @param  list<ResponseInterface>  $responses
     * @param  array<int, Throwable>  $dispatchErrors
     * @param  array<int, string>  $chunkErrors
     */
    public function __construct(
        private readonly array $responses,
        private readonly array $dispatchErrors = [],
        private readonly array $chunkErrors = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requestOptions[] = $options;
        $index = $this->requestIndex++;

        if (isset($this->dispatchErrors[$index])) {
            throw $this->dispatchErrors[$index];
        }

        return $this->responses[$index];
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $pairs = [];

        foreach ($responses as $index => $response) {
            $pairs[] = [$response, new BatchChunk(true, $this->chunkErrors[$index] ?? null)];
        }

        return new BatchResponseStream($pairs);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        return $this;
    }
}
