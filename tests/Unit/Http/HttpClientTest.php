<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\Http\Response;
use EasyUmsPay\Tests\Support\BatchSpyHttpClient;
use EasyUmsPay\Tests\Support\SpyHttpClient;
use EasyUmsPay\Tests\Support\SpyResponse;
use EasyUmsPay\Tests\Support\TestLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('可以发送表单 POST 请求', function () {
    $capturedMethod = null;
    $capturedBody = null;
    $capturedHeaders = [];
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedBody, &$capturedHeaders) {
        $capturedMethod = $method;
        $capturedBody = is_string($options['body'] ?? null) ? $options['body'] : null;
        $headers = $options['normalized_headers'] ?? [];
        $capturedHeaders = is_array($headers) ? $headers : [];

        return new MockResponse('{"status":"success"}');
    });

    $client = new HttpClient($mockHttpClient);
    $response = $client->post('/test', ['key' => 'value']);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($capturedMethod)->toBe('POST')
        ->and($capturedBody)->toBe('key=value')
        ->and($capturedHeaders['content-type'][0] ?? null)->toBe('Content-Type: application/x-www-form-urlencoded');
});

test('postForm 兼容表单 POST', function () {
    $capturedMethod = null;
    $capturedBody = null;
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedBody) {
        $capturedMethod = $method;
        $capturedBody = is_string($options['body'] ?? null) ? $options['body'] : null;

        return new MockResponse('{"status":"success"}');
    });

    $client = new HttpClient($mockHttpClient);
    $client->postForm('/test', ['key' => 'value']);

    expect($capturedMethod)->toBe('POST')
        ->and($capturedBody)->toBe('key=value');
});

test('可以发送 JSON POST 请求并支持附加 options', function () {
    $capturedMethod = null;
    $capturedBody = null;
    $capturedHeaders = [];
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedBody, &$capturedHeaders) {
        $capturedMethod = $method;
        $capturedBody = is_string($options['body'] ?? null) ? $options['body'] : null;
        $headers = $options['normalized_headers'] ?? [];
        $capturedHeaders = is_array($headers) ? $headers : [];

        return new MockResponse('{"status":"success"}');
    });

    $client = new HttpClient($mockHttpClient);
    $response = $client->postJson('/test', ['key' => 'value'], [
        'headers' => [
            'OPEN-BODY-SIG' => 'signed-body',
            'OPEN-APP-ID' => 'app-id',
        ],
    ]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($capturedMethod)->toBe('POST')
        ->and($capturedBody)->toBe('{"key":"value"}')
        ->and($capturedHeaders['content-type'][0] ?? null)->toBe('Content-Type: application/json')
        ->and($capturedHeaders['open-body-sig'][0] ?? null)->toBe('OPEN-BODY-SIG: signed-body')
        ->and($capturedHeaders['open-app-id'][0] ?? null)->toBe('OPEN-APP-ID: app-id');
});

test('可以发送 GET 请求并使用简写 query', function () {
    $capturedMethod = null;
    $capturedQuery = null;
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedQuery) {
        $capturedMethod = $method;
        $capturedQuery = is_array($options['query'] ?? null) ? $options['query'] : null;

        return new MockResponse('{"data":"test"}');
    });

    $client = new HttpClient($mockHttpClient);
    $client->get('/test', ['param' => 'value']);

    expect($capturedMethod)->toBe('GET')
        ->and($capturedQuery)->toBe(['param' => 'value']);
});

test('GET 显式 query 与简写参数会合并', function () {
    $capturedQuery = null;
    $capturedTimeout = null;
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedQuery, &$capturedTimeout) {
        $capturedQuery = is_array($options['query'] ?? null) ? $options['query'] : null;
        $capturedTimeout = is_float($options['timeout'] ?? null) ? $options['timeout'] : null;

        return new MockResponse('{}');
    });

    $client = new HttpClient($mockHttpClient);
    $client->get('/test', [
        'query' => ['a' => '1'],
        'b' => '2',
        'timeout' => 5,
    ]);

    expect($capturedQuery)->toBe(['a' => '1', 'b' => '2'])
        ->and($capturedTimeout)->toBe(5.0);
});

test('patch、put、delete 使用正确的 HTTP 方法', function () {
    $methods = [];
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$methods) {
        $methods[] = $method;

        return new MockResponse('{}');
    });

    $client = new HttpClient($mockHttpClient);
    $client->patch('/patch', ['a' => '1']);
    $client->put('/put', ['b' => '2']);
    $client->delete('/delete', ['id' => '3']);

    expect($methods)->toBe(['PATCH', 'PUT', 'DELETE']);
});

test('patchJson 发送 JSON PATCH', function () {
    $capturedMethod = null;
    $capturedBody = null;
    $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedBody) {
        $capturedMethod = $method;
        $capturedBody = is_string($options['body'] ?? null) ? $options['body'] : null;

        return new MockResponse('{}');
    });

    $client = new HttpClient($mockHttpClient);
    $client->patchJson('/test', ['key' => 'value']);

    expect($capturedMethod)->toBe('PATCH')
        ->and($capturedBody)->toBe('{"key":"value"}');
});

test('JSON 编码失败时抛出请求异常', function () {
    $client = new HttpClient(new MockHttpClient(new MockResponse('{}')));

    expect(fn () => $client->postJson('/test', ['invalid' => "\xB1\x31"]))
        ->toThrow(RequestException::class, 'JSON encoding failed');
});

test('网络失败时抛出请求异常', function () {
    $mockHttpClient = new MockHttpClient(function (): never {
        throw new TransportException('Connection failed');
    });

    $client = new HttpClient($mockHttpClient);

    expect(fn () => $client->postForm('/test', ['key' => 'value']))
        ->toThrow(RequestException::class, 'Connection failed');
});

test('底层 client 支持 LoggerAwareInterface 时会接收 logger', function () {
    $logger = new TestLogger;
    $spy = new SpyHttpClient(new SpyResponse('{}'));

    new HttpClient($spy, $logger);

    expect($spy->logger)->toBe($logger);
});

test('底层 client 不支持 LoggerAwareInterface 时静默跳过', function () {
    $client = new HttpClient(new MockHttpClient(new MockResponse('{}')), new TestLogger);

    expect($client)->toBeInstanceOf(HttpClient::class);
});

test('返回 Response 前不会访问底层响应', function () {
    $spyResponse = new SpyResponse('{"status":"success"}');
    $client = new HttpClient(new SpyHttpClient($spyResponse), new TestLogger);

    $returned = $client->postJson('/test', ['key' => 'value']);

    expect($returned)->toBeInstanceOf(Response::class)
        ->and($spyResponse->getStatusCodeCalls)->toBe(0)
        ->and($spyResponse->getHeadersCalls)->toBe(0)
        ->and($spyResponse->getContentCalls)->toBe(0);
});

test('无 logger 时不会主动消费响应内容', function () {
    $spyResponse = new SpyResponse('{"status":"success"}');
    $client = new HttpClient(new SpyHttpClient($spyResponse));

    $client->postJson('/test', ['key' => 'value']);

    expect($spyResponse->getStatusCodeCalls)->toBe(0)
        ->and($spyResponse->getHeadersCalls)->toBe(0)
        ->and($spyResponse->getContentCalls)->toBe(0);
});

test('batchPostForm 会按原键名返回结果', function () {
    $httpClient = new BatchSpyHttpClient([
        new MockResponse('{"ok":true}'),
        new MockResponse('{"ok":true}'),
    ]);
    $client = new HttpClient($httpClient);

    $results = $client->batchPostForm('/batch', [
        'license' => ['a' => '1'],
        'id_card' => ['b' => '2'],
    ]);

    $licenseResult = $results['license'];
    $idCardResult = $results['id_card'];

    expect($results)->toHaveKeys(['license', 'id_card']);
    expect($licenseResult['success'])->toBeTrue();

    if (! isset($licenseResult['response'])) {
        throw new RuntimeException('Expected successful batch response for license.');
    }

    expect($licenseResult['response'])->toBeInstanceOf(Response::class);
    expect($idCardResult['success'])->toBeTrue();
    expect($httpClient->requestOptions)->toHaveCount(2);
});

test('batchPostForm 在单项传输失败时仅返回该项错误', function () {
    $httpClient = new BatchSpyHttpClient(
        [new MockResponse('{"ok":true}')],
        [1 => new TransportException('second request failed')],
    );
    $client = new HttpClient($httpClient);

    $results = $client->batchPostForm('/batch', [
        'first' => ['a' => '1'],
        'second' => ['b' => '2'],
    ]);

    $firstResult = $results['first'];
    $secondResult = $results['second'];

    expect($results)->toHaveKeys(['first', 'second']);
    expect($firstResult['success'])->toBeTrue();
    expect($secondResult['success'])->toBeFalse();

    if (! isset($secondResult['error'])) {
        throw new RuntimeException('Expected failed batch error for second request.');
    }

    expect($secondResult['error'])->toBeInstanceOf(RequestException::class);
});

test('batchPostForm 会处理流阶段的单项错误', function () {
    $httpClient = new BatchSpyHttpClient(
        [new MockResponse('{"ok":true}'), new MockResponse('{"ok":true}')],
        [],
        [1 => 'stream failed'],
    );
    $client = new HttpClient($httpClient);

    $results = $client->batchPostForm('/batch', [
        'first' => ['a' => '1'],
        'second' => ['b' => '2'],
    ]);

    $firstResult = $results['first'];
    $secondResult = $results['second'];

    expect($firstResult['success'])->toBeTrue();
    expect($secondResult['success'])->toBeFalse();

    if (! isset($secondResult['error'])) {
        throw new RuntimeException('Expected stream error for second request.');
    }

    expect($secondResult['error'])->toBeInstanceOf(RequestException::class);
    expect($secondResult['error']->getMessage())->toContain('stream failed');
});
