<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Supports\Config;
use EasyUmsPay\Trade\Enums\TradeProtocol;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tradeConfig(array $overrides = []): array
{
    return array_merge([
        'protocol' => 'shopping_cart',
        'base_url' => 'https://test-api-open.chinaums.com',
        'app_id' => 'test_app_id',
        'app_key' => 'test_app_key',
        'merchant_no' => 'test_merchant',
        'terminal_no' => 'test_terminal',
    ], $overrides);
}

test('可以使用必需参数创建配置', function () {
    $config = new Config([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test_accesser',
            'sign_key' => '123456789012345678901234',
        ],
        'trade' => tradeConfig(),
    ]);

    expect($config)->toBeInstanceOf(Config::class)
        ->and($config->getProtocol())->toBe(TradeProtocol::ShoppingCart);
});

test('日志默认关闭', function () {
    expect((new Config([]))->isLogEnabled())->toBeFalse();
});

test('未配置日志时 getLog 返回 null', function () {
    expect((new Config([]))->getLog())->toBeNull();
});

test('未配置日志时默认通道和通道配置为 null', function () {
    $config = new Config([]);

    expect($config->getLogDefaultChannel())->toBeNull()
        ->and($config->getLogChannel('stderr'))->toBeNull();
});

test('日志配置原样保留', function () {
    $log = [
        'enabled' => true,
        'default' => 'stderr',
        'channels' => [
            'stderr' => ['driver' => 'stderr', 'level' => 'debug'],
        ],
    ];

    expect((new Config(['log' => $log]))->getLog())->toBe($log);
});

test('可以禁用日志', function () {
    $config = new Config(['log' => ['enabled' => false]]);

    expect($config->isLogEnabled())->toBeFalse();
});

test('可以获取日志默认通道', function () {
    $config = new Config(['log' => ['default' => 'daily']]);

    expect($config->getLogDefaultChannel())->toBe('daily');
});

test('可以获取日志通道配置', function () {
    $config = new Config([
        'log' => [
            'channels' => [
                'stderr' => ['driver' => 'stderr', 'level' => 'debug'],
            ],
        ],
    ]);

    $channel = $config->getLogChannel('stderr');

    expect($channel)->toBeArray();

    if (is_array($channel)) {
        expect($channel['driver'])->toBe('stderr')
            ->and($channel['level'])->toBe('debug');
    }
});

test('可以获取自助签约配置', function () {
    $selfContractConfig = [
        'base_url' => 'https://selfapply-test.chinaums.com',
        'accesser_id' => 'test_accesser',
        'sign_key' => '123456789012345678901234',
    ];

    $config = new Config(['self_contract' => $selfContractConfig]);

    expect($config->getSelfContract())->toBe($selfContractConfig);
});

test('可以获取交易配置', function () {
    $trade = tradeConfig(['protocol' => 'netpay']);

    $config = new Config(['trade' => $trade]);

    expect($config->getTrade())->toBe($trade)
        ->and($config->getProtocol())->toBe(TradeProtocol::NetPay);
});

test('可以获取HTTP配置', function () {
    $config = new Config([
        'http' => ['timeout' => 15, 'verify_peer' => false],
    ]);

    expect($config->getHttp())->toBe(['timeout' => 15, 'verify_peer' => false]);
});

test('http.throw 默认启用', function () {
    expect((new Config([]))->isHttpThrowEnabled())->toBeTrue();
});

test('可以关闭 http.throw', function () {
    $config = new Config(['http' => ['throw' => false]]);

    expect($config->isHttpThrowEnabled())->toBeFalse();
});
test('HTTP 日志默认关闭', function () {
    $logging = (new Config([]))->getHttpLogging();

    expect($logging->request)->toBeFalse()
        ->and($logging->response)->toBeFalse()
        ->and($logging->body)->toBeFalse();
});

test('可以分别开启 HTTP 日志', function () {
    $logging = (new Config([
        'log' => [
            'http' => [
                'request' => true,
                'response' => true,
                'body' => true,
            ],
        ],
    ]))->getHttpLogging();

    expect($logging->request)->toBeTrue()
        ->and($logging->response)->toBeTrue()
        ->and($logging->body)->toBeTrue();
});

test('自助签约配置非空时所有声明字段必填', function () {
    expect(fn () => new Config([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test_accesser',
        ],
    ]))->toThrow(ValidationException::class, '缺少配置项: self_contract.sign_key');
});

test('交易配置非空时所有声明字段必填', function () {
    expect(fn () => new Config([
        'trade' => [
            'protocol' => 'shopping_cart',
            'base_url' => 'https://test-api-open.chinaums.com',
            'app_id' => 'test_app_id',
            'merchant_no' => 'test_merchant',
            'terminal_no' => 'test_terminal',
        ],
    ]))->toThrow(ValidationException::class, '缺少配置项: trade.app_key');
});

test('交易协议缺失或非法时抛异常', function () {
    expect(fn () => new Config(['trade' => tradeConfig(['protocol' => 'legacy'])]))
        ->toThrow(ValidationException::class);

    expect(fn () => new Config(['trade' => tradeConfig(['protocol' => ''])]))
        ->toThrow(ValidationException::class);
});

test('空交易配置也会触发必填校验', function () {
    expect(fn () => new Config(['trade' => []]))
        ->toThrow(ValidationException::class, '缺少配置项: trade.protocol');
});
