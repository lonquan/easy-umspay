<?php

declare(strict_types=1);

use EasyUmsPay\Tests\Support\TestLogger;
use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\UmsPay;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @param  array<int, array{level: string, message: string, context: array<string, mixed>}>  $records
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function loggerRecordsByMessage(array $records, string $message): array
{
    return array_values(array_filter(
        $records,
        static fn (array $record): bool => $record['message'] === $message,
    ));
}

test('Trade 请求会写入 logger', function () {
    $logger = new TestLogger;
    $http = new MockHttpClient(new MockResponse('{"respCode":"0000","status":"NEW_ORDER"}'));

    $umsPay = new UmsPay([
        'log' => [
            'http' => [
                'request' => true,
                'response' => false,
                'body' => true,
            ],
        ],
        'trade' => [
            'protocol' => 'shopping_cart',
            'base_url' => 'https://test-api-open.chinaums.com',
            'app_id' => 'app-id',
            'app_key' => 'app-key',
            'merchant_no' => 'M1000',
            'terminal_no' => 'T0001',
        ],
    ], $logger, $http);

    $umsPay->trade()->pay([
        'orderNo' => 'ORDER-LOG-1',
        'amount' => 100,
        'description' => '日志测试',
        'notifyUrl' => 'https://example.com/notify',
    ], PayScene::WeChatMiniApp);

    $records = loggerRecordsByMessage($logger->records, 'HTTP Request');

    expect($records)->toHaveCount(1)
        ->and($records[0]['context']['method'])->toBe('POST')
        ->and($records[0]['context']['url'])->toBe('https://test-api-open.chinaums.com/v1/inip/upsp/shop/mini/pay')
        ->and($records[0]['context']['payload'])->toMatchArray([
            'mchntOrderId' => 'ORDER-LOG-1',
        ]);
});

test('SelfContract 请求会写入 HTTP 日志', function () {
    $logger = new TestLogger;
    $http = new MockHttpClient(new MockResponse('{"res_code":"0000"}'));

    $umsPay = new UmsPay([
        'log' => [
            'http' => [
                'request' => true,
                'response' => false,
                'body' => true,
            ],
        ],
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test',
            'sign_key' => '123456789012345678901234',
        ],
    ], $logger, $http);

    $umsPay->selfContract()->uploadImage(selfContractSamplePngBase64());

    $records = loggerRecordsByMessage($logger->records, 'HTTP Request');

    expect($records)->toHaveCount(1)
        ->and($records[0]['context']['method'])->toBe('POST')
        ->and($records[0]['context']['url'])->toBe('https://selfapply-test.chinaums.com/self-contract-nmrs/interface/autoReg')
        ->and($records[0]['context']['payload'])->toHaveKeys(['json_data', 'sign_data', 'accesser_id'])
        ->and($records[0]['context']['payload']['accesser_id'])->toBe('test');
});

test('文件日志会真实写入磁盘', function () {
    $logPath = dirname(__DIR__, 2) . '/logs/ums-file-log-test-' . uniqid('', true) . '.log';

    try {
        $umsPay = new UmsPay([
            'log' => [
                'enabled' => true,
                'default' => 'single',
                'channels' => [
                    'single' => [
                        'driver' => 'single',
                        'path' => $logPath,
                        'level' => 'debug',
                        'replace_placeholders' => true,
                    ],
                ],
                'http' => [
                    'request' => true,
                    'response' => true,
                    'body' => true,
                ],
            ],
            'trade' => [
                'protocol' => 'shopping_cart',
                'base_url' => 'https://test-api-open.chinaums.com',
                'app_id' => 'app-id',
                'app_key' => 'app-key',
                'merchant_no' => 'M1000',
                'terminal_no' => 'T0001',
            ],
        ], null, new MockHttpClient(new MockResponse('{"respCode":"0000","status":"NEW_ORDER"}')));

        $umsPay->trade()->pay([
            'orderNo' => 'ORDER-FILE-LOG',
            'amount' => 100,
            'description' => '文件日志测试',
            'notifyUrl' => 'https://example.com/notify',
        ], PayScene::WeChatMiniApp);

        $content = file_get_contents($logPath);

        if (! is_string($content)) {
            throw new RuntimeException('Cannot read log file.');
        }

        expect($content)->toContain('HTTP Request')
            ->and($content)->toContain('HTTP Response Headers')
            ->and($content)->toContain('HTTP Response Body')
            ->and($content)->toContain('ORDER-FILE-LOG');
    } finally {
        @unlink($logPath);
    }
});
