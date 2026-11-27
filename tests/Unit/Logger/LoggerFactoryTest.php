<?php

declare(strict_types=1);

use EasyUmsPay\Logger\LoggerFactory;
use EasyUmsPay\Supports\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

function loggerStreamHandler(Logger $logger): StreamHandler
{
    $handler = $logger->getHandlers()[0] ?? null;

    expect($handler)->toBeInstanceOf(StreamHandler::class);

    assert($handler instanceof StreamHandler);

    return $handler;
}

test('没有日志配置时降级到 stderr/info', function () {
    $logger = LoggerFactory::createDefault(new Config([]));

    expect($logger)->toBeInstanceOf(Logger::class);

    assert($logger instanceof Logger);

    $handler = loggerStreamHandler($logger);

    expect($handler->getUrl())->toBe('php://stderr')
        ->and($handler->getLevel())->toBe(Level::Info);
});

test('启用日志但没有可用通道时降级到 stderr/info', function () {
    $logger = LoggerFactory::createDefault(new Config([
        'log' => ['enabled' => true],
    ]));

    assert($logger instanceof Logger);

    $handler = loggerStreamHandler($logger);

    expect($handler->getUrl())->toBe('php://stderr')
        ->and($handler->getLevel())->toBe(Level::Info);
});

test('默认通道缺失但配置了 stderr 通道时使用 stderr 通道配置', function () {
    $logger = LoggerFactory::createDefault(new Config([
        'log' => [
            'enabled' => true,
            'channels' => [
                'stderr' => ['driver' => 'stderr', 'level' => 'warning'],
            ],
        ],
    ]));

    assert($logger instanceof Logger);

    $handler = loggerStreamHandler($logger);

    expect($handler->getUrl())->toBe('php://stderr')
        ->and($handler->getLevel())->toBe(Level::Warning);
});

test('默认通道指向不存在的通道时降级到 stderr/info', function () {
    $logger = LoggerFactory::createDefault(new Config([
        'log' => [
            'enabled' => true,
            'default' => 'daily',
        ],
    ]));

    assert($logger instanceof Logger);

    $handler = loggerStreamHandler($logger);

    expect($handler->getUrl())->toBe('php://stderr')
        ->and($handler->getLevel())->toBe(Level::Info);
});

test('默认通道存在时使用该通道配置', function () {
    $logger = LoggerFactory::createDefault(new Config([
        'log' => [
            'enabled' => true,
            'default' => 'custom',
            'channels' => [
                'custom' => ['driver' => 'stderr', 'level' => 'warning'],
            ],
        ],
    ]));

    assert($logger instanceof Logger);

    $handler = loggerStreamHandler($logger);

    expect($handler->getUrl())->toBe('php://stderr')
        ->and($handler->getLevel())->toBe(Level::Warning);
});
