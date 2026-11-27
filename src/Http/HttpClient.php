<?php

declare(strict_types=1);

namespace EasyUmsPay\Http;

use Closure;
use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Http\Internal\LoggingResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_replace;
use function get_debug_type;
use function http_build_query;
use function in_array;
use function is_array;
use function is_iterable;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function parse_str;
use function sprintf;
use function str_contains;
use function strtolower;
use function strtoupper;

/**
 * EasyWeChat 风格的 API Client。
 *
 * 负责请求组装、发送和响应包装。
 * 可选记录完整请求日志；响应日志由 LoggingResponse 在访问时惰性记录。
 * logger 同时会传递给实现 LoggerAwareInterface 的底层 client。
 */
readonly class HttpClient
{
    public function __construct(
        protected HttpClientInterface $client,
        protected ?LoggerInterface $logger = null,
        protected bool $throw = true,
        protected ?HttpLogging $logging = null,
    ) {
        if ($logger instanceof LoggerInterface && $client instanceof LoggerAwareInterface) {
            $client->setLogger($logger);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $method = strtoupper($method);
        $options = $this->formatOptions($method, $options);

        $this->logRequest($method, $url, $options);

        try {
            $response = $this->client->request($method, $url, $options);
        } catch (TransportExceptionInterface $exception) {
            throw new RequestException(
                'HTTP request failed: ' . $exception->getMessage(),
                null,
                ['uri' => $url, 'method' => $method],
                $exception,
            );
        }

        return new Response($this->wrapResponse($response, $method, $url), throw: $this->throw);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function get(string $uri, array $options = []): Response
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function post(string $uri, array $options = []): Response
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function patch(string $uri, array $options = []): Response
    {
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function put(string $uri, array $options = []): Response
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function delete(string $uri, array $options = []): Response
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function postJson(string $url, array $data = [], array $options = []): Response
    {
        return $this->requestJson('POST', $url, $data, $options);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    public function patchJson(string $url, array $data = [], array $options = []): Response
    {
        return $this->requestJson('PATCH', $url, $data, $options);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RequestException
     */
    public function postForm(string $uri, array $data = []): Response
    {
        return $this->post($uri, ['body' => $data]);
    }

    /**
     * 并发发送多个表单 POST 请求。
     *
     * @param  array<int|string, array<string, mixed>>  $payloads
     * @return array<int|string, array{success: bool, response?: Response, error?: Throwable}>
     *
     * @throws TransportExceptionInterface
     */
    public function batchPostForm(string $uri, array $payloads, ?float $timeout = null): array
    {
        /** @var array<int|string, array{success: bool, response?: Response, error?: Throwable}> $results */
        $results = [];

        /** @var array<int, int|string> $responseKeys */
        $responseKeys = [];

        /** @var list<ResponseInterface> $responses */
        $responses = [];

        $this->logBatchRequest($uri, $payloads);

        foreach ($payloads as $key => $payload) {
            try {
                $response = $this->client->request('POST', $uri, ['body' => $payload]);
                $responses[] = $response;
                $responseKeys[spl_object_id($response)] = $key;
            } catch (TransportExceptionInterface $exception) {
                $results[$key] = [
                    'success' => false,
                    'error' => new RequestException(
                        'HTTP request failed: ' . $exception->getMessage(),
                        null,
                        ['uri' => $uri, 'request_type' => 'form', 'key' => $key],
                        $exception,
                    ),
                ];
            }
        }

        foreach ($this->client->stream($responses, $timeout) as $response => $chunk) {
            $key = $responseKeys[spl_object_id($response)] ?? null;

            if ($key === null || ! $chunk->isLast()) {
                continue;
            }

            $error = $chunk->getError();

            if (is_string($error) && $error !== '') {
                $results[$key] = [
                    'success' => false,
                    'error' => new RequestException(
                        'HTTP request failed: ' . $error,
                        null,
                        ['uri' => $uri, 'request_type' => 'form', 'key' => $key],
                    ),
                ];

                continue;
            }

            $results[$key] = [
                'success' => true,
                'response' => new Response($this->wrapResponse($response, 'POST', $uri), throw: $this->throw),
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     *
     * @throws RequestException
     */
    protected function requestJson(string $method, string $url, array $data, array $options): Response
    {
        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $options['headers'] = $this->withHeader($headers, 'Content-Type', 'application/json');
        $options['json'] = $data;

        return $this->request($method, $url, $options);
    }

    /**
     * 将 EasyWeChat 风格的简写 options 转换为 Symfony HttpClient options。
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    protected function formatOptions(string $method, array $options): array
    {
        $headers = $this->normalizeHeaders($options['headers'] ?? []);

        if (isset($options['headers'])) {
            $options['headers'] = $headers;
        }

        $target = in_array($method, ['GET', 'HEAD', 'DELETE'], true) ? 'query' : 'body';
        $contentType = $this->headerValue($headers, 'content-type');

        if (array_key_exists('json', $options) || ($contentType !== null && str_contains(strtolower($contentType), 'json'))) {
            $target = 'json';
        }

        /** @var array<string, mixed> $extra */
        $extra = [];

        foreach (array_keys($options) as $key) {
            if (! array_key_exists($key, HttpClientInterface::OPTIONS_DEFAULTS)) {
                $extra[$key] = $options[$key];
                unset($options[$key]);
            }
        }

        if ($extra !== []) {
            if ($target === 'json' && array_key_exists('json', $options) && ! is_array($options['json'])) {
                throw new RequestException(
                    'Cannot merge extra options into a pre-encoded JSON body.',
                    null,
                    ['option' => 'json'],
                );
            }

            $current = $options[$target] ?? [];

            if (! is_array($current)) {
                throw new RequestException(
                    sprintf('Cannot merge extra options into the "%s" option.', $target),
                    null,
                    ['option' => $target],
                );
            }

            $options[$target] = array_replace($current, $extra);
        }

        if (array_key_exists('json', $options)) {
            if (isset($options['body']) && $options['body'] !== '' && $options['body'] !== []) {
                throw new RequestException(
                    'Cannot use both "json" and "body" options.',
                    null,
                    ['option' => 'json'],
                );
            }

            $json = $options['json'];

            if (is_array($json)) {
                $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded === false) {
                    throw new RequestException(
                        'JSON encoding failed.',
                        null,
                        ['option' => 'json'],
                    );
                }
            } elseif (is_string($json)) {
                $encoded = $json;
            } else {
                throw new RequestException(
                    'The "json" option must be an array or string.',
                    null,
                    ['option' => 'json'],
                );
            }

            $options['body'] = $encoded;
            unset($options['json']);
            $headers = $this->withHeader($headers, 'Content-Type', 'application/json');
            $options['headers'] = $headers;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function logRequest(string $method, string $url, array $options): void
    {
        $logging = $this->logging;

        if ($this->logger === null || $logging === null || ! $logging->request) {
            return;
        }

        try {
            $query = is_array($options['query'] ?? null) ? $options['query'] : [];
            $context = [
                'method' => $method,
                'url' => $url,
                'query' => $options['query'] ?? [],
                'full_url' => $this->fullUrl($url, $query),
                'headers' => is_array($options['headers'] ?? null) ? $options['headers'] : [],
                'options' => $this->sanitizeForLog($this->loggableOptions($options)),
            ];

            if ($logging->body) {
                $context['payload'] = $this->formatPayloadForLog($options);
            }

            $this->logger->debug('HTTP Request', $context);
        } catch (Throwable) {
            // 日志失败不影响请求。
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $payloads
     */
    protected function logBatchRequest(string $url, array $payloads): void
    {
        $logging = $this->logging;

        if ($this->logger === null || $logging === null || ! $logging->request) {
            return;
        }

        try {
            $this->logger->debug('HTTP Batch Request', [
                'method' => 'POST',
                'url' => $url,
                'count' => count($payloads),
                'keys' => array_keys($payloads),
            ]);
        } catch (Throwable) {
            // 日志失败不影响请求。
        }
    }

    protected function wrapResponse(ResponseInterface $response, string $method, string $url): ResponseInterface
    {
        $logging = $this->logging;

        if ($this->logger === null || $logging === null || ! $logging->response) {
            return $response;
        }

        return new LoggingResponse($response, $this->logger, $logging, $method, $url);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function loggableOptions(array $options): array
    {
        if ($this->logging?->body !== true) {
            unset($options['body'], $options['json']);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function formatPayloadForLog(array $options): mixed
    {
        if (! array_key_exists('body', $options)) {
            return null;
        }

        $body = $options['body'];

        if (! is_string($body)) {
            return $this->sanitizeForLog($body);
        }

        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $contentType = $this->headerValue($headers, 'content-type');

        if ($contentType !== null && str_contains(strtolower($contentType), 'json')) {
            $decoded = json_decode($body, true);

            return $decoded ?? $body;
        }

        if ($contentType !== null && str_contains(strtolower($contentType), 'x-www-form-urlencoded')) {
            parse_str($body, $parsed);

            return $parsed;
        }

        return $body;
    }

    protected function fullUrl(string $url, mixed $query): string
    {
        if (! is_array($query) || $query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    protected function sanitizeForLog(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 5) {
            return '[depth]';
        }

        if ($value instanceof Closure) {
            return '[callable]';
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeForLog($item, $depth + 1);
            }

            return $sanitized;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeHeaders(mixed $headers): array
    {
        if (is_iterable($headers) && ! is_array($headers)) {
            $headers = iterator_to_array($headers);
        }

        return is_array($headers) ? $headers : [];
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function withHeader(array $headers, string $name, string $value): array
    {
        $lowerName = strtolower($name);

        foreach (array_keys($headers) as $key) {
            if (strtolower((string) $key) === $lowerName) {
                $headers[$key] = $value;

                return $headers;
            }
        }

        $headers[$name] = $value;

        return $headers;
    }
}
