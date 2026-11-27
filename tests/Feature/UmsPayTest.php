<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\SelfContract\Client;
use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;
use EasyUmsPay\Supports\Config;
use EasyUmsPay\Trade\Enums\TradeProtocol;
use EasyUmsPay\Trade\TradeClient;
use EasyUmsPay\UmsPay;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

afterEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

test('可以使用数组配置创建客户端', function () {
    $client = new UmsPay([]);

    expect($client)->toBeInstanceOf(UmsPay::class);
});

test('可以使用配置对象创建客户端', function () {
    $config = new Config([]);

    $client = new UmsPay($config);

    expect($client)->toBeInstanceOf(UmsPay::class);
});

test('可以访问自助签约客户端', function () {
    $client = new UmsPay([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test',
            'sign_key' => '123456789012345678901234',
        ],
    ]);

    $selfContract = $client->selfContract();

    expect($selfContract)->toBeInstanceOf(Client::class);
});

test('自助签约客户端是单例', function () {
    $client = new UmsPay([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test',
            'sign_key' => '123456789012345678901234',
        ],
    ]);

    $selfContract1 = $client->selfContract();
    $selfContract2 = $client->selfContract();

    expect($selfContract1)->toBe($selfContract2);
});

test('可以获取配置', function () {
    $client = new UmsPay([]);

    $config = $client->getConfig();

    expect($config)->toBeInstanceOf(Config::class);
});

test('可以获取 API Client', function () {
    $client = new UmsPay([]);

    expect($client->getApiClient())->toBeInstanceOf(HttpClient::class)
        ->and($client->getApiClient())->toBe($client->getApiClient());
});

test('禁用时日志记录器为null', function () {
    $client = new UmsPay([
        'log' => [
            'enabled' => false,
        ],
    ]);

    expect($client->getLogger())->toBeNull();
});

test('未配置日志时日志记录器为null', function () {
    $client = new UmsPay([]);

    expect($client->getLogger())->toBeNull();
});

test('启用时创建日志记录器', function () {
    $client = new UmsPay([
        'log' => [
            'enabled' => true,
            'default' => 'stderr',
            'channels' => [
                'stderr' => [
                    'driver' => 'stderr',
                    'level' => 'info',
                ],
            ],
        ],
    ]);

    expect($client->getLogger())->not->toBeNull()
        ->and($client->getLogger())->toBeInstanceOf(LoggerInterface::class);
});

test('启用日志但未配置通道时创建降级日志记录器', function () {
    $client = new UmsPay([
        'log' => [
            'enabled' => true,
        ],
    ]);

    expect($client->getLogger())->not->toBeNull()
        ->and($client->getLogger())->toBeInstanceOf(LoggerInterface::class);
});

test('缺少自助签约基础URL时抛出异常', function () {
    expect(fn () => new UmsPay([
        'self_contract' => [
            'accesser_id' => 'test',
            'sign_key' => '123456789012345678901234',
        ],
    ]))
        ->toThrow(ValidationException::class, '缺少配置项: self_contract.base_url');
});

test('缺少交易基础URL时抛异常', function () {
    expect(fn () => new UmsPay([
        'trade' => [
            'protocol' => 'shopping_cart',
            'app_id' => 'test_app_id',
            'app_key' => 'test_app_key',
            'merchant_no' => 'test_merchant',
            'terminal_no' => 'test_terminal',
        ],
    ]))
        ->toThrow(ValidationException::class, '缺少配置项: trade.base_url');
});

test('可以按协议获取交易客户端', function () {
    $client = new UmsPay([
        'trade' => [
            'protocol' => 'netpay',
            'base_url' => 'https://test-api-open.chinaums.com',
            'app_id' => 'test_app_id',
            'app_key' => 'test_app_key',
            'merchant_no' => 'M123',
            'terminal_no' => 'T001',
        ],
    ]);

    expect($client->trade())->toBeInstanceOf(TradeClient::class)
        ->and($client->trade()->protocol())->toBe(TradeProtocol::NetPay)
        ->and($client->trade())->toBe($client->trade());
});

test('未配置交易协议时获取交易客户端抛异常', function () {
    expect(fn () => (new UmsPay([]))->trade())
        ->toThrow(ValidationException::class, '缺少交易配置 trade.protocol');
});

test('空模块配置也会触发必填校验', function () {
    expect(fn () => new UmsPay([
        'self_contract' => [],
    ]))
        ->toThrow(ValidationException::class, '缺少配置项: self_contract.base_url');
});

test('SelfContract 使用自定义流水号生成器', function () {
    $calls = 0;

    RequestSequenceGenerator::setGenerator(function () use (&$calls): string {
        $calls++;

        return 'CUSTOM_SEQ_001';
    });

    $client = new UmsPay([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test',
            'sign_key' => '123456789012345678901234',
        ],
    ], http: new MockHttpClient(new MockResponse('{"res_code":"0000"}')));

    $client->selfContract()->uploadImage(selfContractSamplePngBase64());

    expect($calls)->toBe(1);
});
