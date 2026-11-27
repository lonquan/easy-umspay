<?php

declare(strict_types=1);

use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\Http\HttpLogging;
use EasyUmsPay\Tests\Support\SpyHttpClient;
use EasyUmsPay\Tests\Support\SpyResponse;
use EasyUmsPay\Tests\Support\TestLogger;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @param  array<int, array{level: string, message: string, context: array<string, mixed>}>  $records
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function httpLogRecords(array $records, string $message): array
{
    return array_values(array_filter(
        $records,
        static fn (array $record): bool => $record['message'] === $message,
    ));
}

test('请求日志记录 URL、headers、payload 和 options', function () {
    $logger = new TestLogger;
    $spyResponse = new SpyResponse('{"ok":true}');
    $client = new HttpClient(
        new SpyHttpClient($spyResponse),
        $logger,
        logging: new HttpLogging(request: true, response: false, body: true),
    );

    $client->postJson(
        'https://example.com/api',
        ['orderNo' => 'ORDER-1'],
        [
            'query' => ['page' => 1],
            'headers' => ['X-Test' => 'value'],
            'timeout' => 5,
        ],
    );

    $records = httpLogRecords($logger->records, 'HTTP Request');

    expect($records)->toHaveCount(1)
        ->and($records[0]['context'])->toMatchArray([
            'method' => 'POST',
            'url' => 'https://example.com/api',
            'query' => ['page' => 1],
            'full_url' => 'https://example.com/api?page=1',
            'payload' => ['orderNo' => 'ORDER-1'],
        ])
        ->and($records[0]['context']['headers'])->toMatchArray(['X-Test' => 'value'])
        ->and($records[0]['context']['options']['timeout'])->toBe(5)
        ->and($spyResponse->getStatusCodeCalls)->toBe(0)
        ->and($spyResponse->getHeadersCalls)->toBe(0)
        ->and($spyResponse->getContentCalls)->toBe(0);
});

test('响应日志只在访问响应后记录', function () {
    $logger = new TestLogger;
    $spyResponse = new SpyResponse('{"ok":true}');
    $client = new HttpClient(
        new SpyHttpClient($spyResponse),
        $logger,
        logging: new HttpLogging(request: true, response: true, body: true),
    );

    $response = $client->postJson('https://example.com/api', ['orderNo' => 'ORDER-2']);

    expect(httpLogRecords($logger->records, 'HTTP Response Headers'))->toBe([])
        ->and(httpLogRecords($logger->records, 'HTTP Response Body'))->toBe([]);

    $response->getStatusCode();

    expect(httpLogRecords($logger->records, 'HTTP Response Headers'))->toHaveCount(1)
        ->and(httpLogRecords($logger->records, 'HTTP Response Body'))->toBe([])
        ->and($spyResponse->getContentCalls)->toBe(0);

    $response->getContent();

    $bodyLogs = httpLogRecords($logger->records, 'HTTP Response Body');

    expect(httpLogRecords($logger->records, 'HTTP Response Headers'))->toHaveCount(1)
        ->and($bodyLogs)->toHaveCount(1)
        ->and($bodyLogs[0]['context']['body'])->toBe(['ok' => true])
        ->and($spyResponse->getContentCalls)->toBe(1);
});

test('log.http.body=false 不记录请求和响应 body', function () {
    $logger = new TestLogger;
    $client = new HttpClient(
        new SpyHttpClient(new SpyResponse('{"ok":true}')),
        $logger,
        logging: new HttpLogging(request: true, response: true, body: false),
    );

    $response = $client->postJson('https://example.com/api', ['orderNo' => 'ORDER-3']);
    $response->getContent();

    $requestLogs = httpLogRecords($logger->records, 'HTTP Request');
    $bodyLogs = httpLogRecords($logger->records, 'HTTP Response Body');

    expect($requestLogs)->toHaveCount(1)
        ->and($requestLogs[0]['context'])->not->toHaveKey('payload')
        ->and($bodyLogs)->toBe([])
        ->and(httpLogRecords($logger->records, 'HTTP Response Headers'))->toHaveCount(1);
});

test('log.http.request=false 不记录请求日志', function () {
    $logger = new TestLogger;
    $client = new HttpClient(
        new SpyHttpClient(new SpyResponse('{}')),
        $logger,
        logging: new HttpLogging(request: false, response: true, body: true),
    );

    $client->get('https://example.com/api');

    expect(httpLogRecords($logger->records, 'HTTP Request'))->toBe([]);
});
test('HTTP 错误时仍记录可用的响应头与正文', function () {
    $logger = new TestLogger;
    $client = new HttpClient(
        new MockHttpClient(new MockResponse('error-body', ['http_code' => 400])),
        $logger,
        logging: new HttpLogging(request: false, response: true, body: true),
    );

    $response = $client->get('https://example.com/error');

    expect(fn () => $response->getContent())->toThrow(ClientException::class);

    $bodyLogs = httpLogRecords($logger->records, 'HTTP Response Body');

    expect(httpLogRecords($logger->records, 'HTTP Response Headers'))->toHaveCount(1)
        ->and($bodyLogs)->toHaveCount(1)
        ->and($bodyLogs[0]['context']['body'])->toBe('error-body');
});
