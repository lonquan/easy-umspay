<?php

declare(strict_types=1);

namespace EasyUmsPay\Http;

use ArrayAccess;
use Closure;
use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Exceptions\UmsException;
use JsonException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function array_key_exists;
use function base64_encode;
use function file_put_contents;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function str_contains;
use function strtolower;

/**
 * EasyWeChat 风格的响应包装。
 *
 * 只做访问适配，不主动读取响应内容；是否抛出 HTTP 异常由 throw 状态控制。
 *
 * @implements ArrayAccess<array-key, mixed>
 */
final class Response implements ArrayAccess, ResponseInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private ?Closure $failureJudge = null,
        private bool $throw = true,
    ) {
    }

    public function throw(bool $throw = true): static
    {
        $this->throw = $throw;

        return $this;
    }

    public function throwOnFailure(): static
    {
        return $this->throw(true);
    }

    public function quietly(): static
    {
        return $this->throw(false);
    }

    /**
     * @param  callable(self): bool  $callback
     */
    public function judgeFailureUsing(callable $callback): static
    {
        $this->failureJudge = $callback instanceof Closure
            ? $callback
            : static fn (self $response): bool => (bool) $callback($response);

        return $this;
    }

    public function isSuccessful(): bool
    {
        return ! $this->isFailed();
    }

    public function isFailed(): bool
    {
        try {
            if ($this->getStatusCode() >= 400) {
                return true;
            }
        } catch (Throwable) {
            return true;
        }

        if ($this->failureJudge === null) {
            return false;
        }

        try {
            if (! $this->is('text')) {
                return false;
            }

            return (bool) ($this->failureJudge)($this);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function toArray(?bool $throw = null): array
    {
        $content = $this->getContent($throw);

        if ($content === '') {
            throw new RequestException('Response body is empty.', $this, ['content' => '']);
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RequestException(
                'Response body is not valid JSON.',
                $this,
                ['content' => $content],
                $exception,
            );
        }

        if (! is_array($data)) {
            throw new RequestException(
                'Response body is not a JSON array.',
                $this,
                ['content' => $content],
            );
        }

        return $data;
    }

    public function toJson(?bool $throw = null): string|false
    {
        return json_encode(
            $this->toArray($throw),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * 将响应内容保存到文件，返回文件路径。
     *
     * @throws RequestException
     */
    public function saveAs(string $filename): string
    {
        if (file_put_contents($filename, $this->getContent()) === false) {
            throw new RequestException(
                'Failed to save response content.',
                $this,
                ['filename' => $filename],
            );
        }

        return $filename;
    }

    public function toDataUrl(): string
    {
        $contentType = $this->getHeaderLine('content-type') ?: 'application/octet-stream';

        return 'data:' . $contentType . ';base64,' . base64_encode($this->getContent());
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(?bool $throw = null): array
    {
        return $this->response->getHeaders($throw ?? $this->throw);
    }

    public function getContent(?bool $throw = null): string
    {
        return $this->response->getContent($throw ?? $this->throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    public function hasHeader(string $name, ?bool $throw = null): bool
    {
        return array_key_exists(strtolower($name), $this->getHeaders($throw));
    }

    /**
     * @return list<string>
     */
    public function getHeader(string $name, ?bool $throw = null): array
    {
        $headers = $this->getHeaders($throw);

        return $headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name, ?bool $throw = null): string
    {
        return implode(',', $this->getHeader($name, $throw));
    }

    public function is(string $type): bool
    {
        $contentType = strtolower($this->getHeaderLine('content-type', false));

        return match (strtolower($type)) {
            'json' => str_contains($contentType, '/json'),
            'xml' => str_contains($contentType, '/xml'),
            'html' => str_contains($contentType, '/html'),
            'image' => str_contains($contentType, 'image/'),
            'audio' => str_contains($contentType, 'audio/'),
            'video' => str_contains($contentType, 'video/'),
            'text' => str_contains($contentType, 'text/')
                || str_contains($contentType, '/json')
                || str_contains($contentType, '/xml'),
            default => false,
        };
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new UmsException('Response is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new UmsException('Response is immutable.');
    }

    public function __toString(): string
    {
        try {
            return $this->toJson() ?: '';
        } catch (Throwable) {
            return '';
        }
    }
}
