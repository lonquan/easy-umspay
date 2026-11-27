<?php

declare(strict_types=1);

namespace EasyUmsPay\Http\Internal;

use EasyUmsPay\Http\HttpLogging;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function is_array;
use function json_decode;
use function parse_str;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * 惰性记录响应头与响应正文。
 *
 * @internal
 */
final class LoggingResponse implements ResponseInterface
{
    private bool $headersLogged = false;

    private bool $bodyLogged = false;

    public function __construct(
        private readonly ResponseInterface $response,
        private readonly LoggerInterface $logger,
        private readonly HttpLogging $logging,
        private readonly string $method,
        private readonly string $url,
    ) {
    }

    public function getStatusCode(): int
    {
        $status = $this->response->getStatusCode();

        $this->logHeaders($status);

        return $status;
    }

    public function getHeaders(bool $throw = true): array
    {
        $headers = $this->response->getHeaders($throw);

        $this->logHeaders(null, $headers);

        return $headers;
    }

    public function getContent(bool $throw = true): string
    {
        try {
            $content = $this->response->getContent($throw);
        } catch (Throwable $exception) {
            $this->logHeaders();

            try {
                $this->logBody($this->response->getContent(false));
            } catch (Throwable) {
                // 连错误响应体都不可读时，只保留 headers 日志并重新抛出原异常。
            }

            throw $exception;
        }

        $this->logHeaders();
        $this->logBody($content);

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent($throw);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $exception;
        }

        if (! is_array($data)) {
            throw new JsonException('Response body is not a JSON array.');
        }

        return $data;
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    /**
     * @param  array<string, list<string>>|null  $headers
     */
    private function logHeaders(?int $status = null, ?array $headers = null): void
    {
        if ($this->headersLogged || ! $this->logging->response) {
            return;
        }

        $this->headersLogged = true;

        try {
            $status ??= $this->response->getStatusCode();
            $headers ??= $this->response->getHeaders(false);

            $this->logger->debug('HTTP Response Headers', [
                'method' => $this->method,
                'url' => $this->url,
                'status' => $status,
                'headers' => $headers,
            ]);
        } catch (Throwable) {
            // 日志失败不影响请求/响应。
        }
    }

    private function logBody(string $content): void
    {
        if ($this->bodyLogged || ! $this->logging->response || ! $this->logging->body) {
            return;
        }

        $this->bodyLogged = true;

        try {
            $contentType = $this->response->getHeaders(false)['content-type'][0] ?? '';
            $normalizedContentType = strtolower($contentType);
            $body = $content;

            if (str_contains($normalizedContentType, 'json')) {
                $decoded = json_decode($content, true);
                $body = $decoded ?? $content;
            } elseif (str_contains($normalizedContentType, 'x-www-form-urlencoded')) {
                parse_str($content, $parsed);
                $body = $parsed;
            } elseif (
                str_contains($normalizedContentType, 'image/')
                || str_contains($normalizedContentType, 'audio/')
                || str_contains($normalizedContentType, 'video/')
                || str_contains($normalizedContentType, 'octet-stream')
            ) {
                $body = '[binary ' . strlen($content) . ' bytes]';
            }

            $this->logger->debug('HTTP Response Body', [
                'method' => $this->method,
                'url' => $this->url,
                'status' => $this->response->getStatusCode(),
                'content_type' => $contentType,
                'body' => $body,
            ]);
        } catch (Throwable) {
            // 日志失败不影响请求/响应。
        }
    }
}
