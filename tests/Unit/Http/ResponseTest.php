<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Exceptions\UmsException;
use EasyUmsPay\Http\Response;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function makeHttpResponse(string $body, int $status = 200, string $contentType = 'application/json'): Response
{
    $client = new MockHttpClient(new MockResponse($body, [
        'http_code' => $status,
        'response_headers' => [
            'content-type' => $contentType,
        ],
    ]));

    return new Response($client->request('GET', 'https://example.com'));
}

test('可以读取状态码、头部与内容', function () {
    $response = makeHttpResponse('{"foo":"bar"}');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('{"foo":"bar"}')
        ->and($response->getHeaders())->toHaveKey('content-type')
        ->and($response->hasHeader('Content-Type'))->toBeTrue()
        ->and($response->getHeader('content-type'))->toBe(['application/json'])
        ->and($response->getHeaderLine('content-type'))->toBe('application/json')
        ->and($response->is('json'))->toBeTrue();
});

test('toArray 与 toJson 按 JSON 处理', function () {
    $response = makeHttpResponse('{"name":"测试","url":"https://example.com/a"}');

    expect($response->toArray())->toBe(['name' => '测试', 'url' => 'https://example.com/a'])
        ->and($response->toJson())->toBe('{"name":"测试","url":"https://example.com/a"}')
        ->and($response['name'])->toBe('测试')
        ->and(isset($response['url']))->toBeTrue()
        ->and($response['missing'])->toBeNull();
});

test('空 body 与非法 JSON 抛出 RequestException', function () {
    expect(fn () => makeHttpResponse('')->toArray())
        ->toThrow(RequestException::class, 'Response body is empty.');

    expect(fn () => makeHttpResponse('not-json')->toArray())
        ->toThrow(RequestException::class, 'Response body is not valid JSON.');
});

test('HTTP 错误状态按 isFailed 判断', function () {
    $response = makeHttpResponse('{"error":"boom"}', 500);

    expect($response->isFailed())->toBeTrue()
        ->and($response->isSuccessful())->toBeFalse();
});

test('failure judge 可判定业务失败', function () {
    $response = makeHttpResponse('{"res_code":"9001","res_msg":"参数错误"}');
    $response->judgeFailureUsing(static fn (Response $item): bool => ($item['res_code'] ?? '') !== '0000');

    expect($response->isFailed())->toBeTrue()
        ->and($response->isSuccessful())->toBeFalse();

    $ok = makeHttpResponse('{"res_code":"0000"}');
    $ok->judgeFailureUsing(static fn (Response $item): bool => ($item['res_code'] ?? '') !== '0000');

    expect($ok->isSuccessful())->toBeTrue();
});

test('quietly 关闭 HTTP 异常抛出', function () {
    $response = makeHttpResponse('{"error":"boom"}', 500);

    expect($response->quietly()->getContent())->toBe('{"error":"boom"}')
        ->and($response->toArray(false))->toBe(['error' => 'boom']);
});

test('响应对象不可写入', function () {
    $response = makeHttpResponse('{"foo":"bar"}');

    expect(fn () => $response['foo'] = 'baz')->toThrow(UmsException::class);
    expect(function () use ($response): void {
        unset($response['foo']);
    })->toThrow(UmsException::class);
});

test('可以保存为文件与 Data URL', function () {
    $response = makeHttpResponse('{"foo":"bar"}');
    $path = tempnam(sys_get_temp_dir(), 'ums-response-');

    if ($path === false) {
        throw new RuntimeException('Cannot create temp file.');
    }

    try {
        expect($response->saveAs($path))->toBe($path)
            ->and(file_get_contents($path))->toBe('{"foo":"bar"}');
    } finally {
        @unlink($path);
    }

    expect($response->toDataUrl())->toStartWith('data:application/json;base64,');
});
