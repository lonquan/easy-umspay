<?php

declare(strict_types=1);

use EasyUmsPay\Http\HttpClientFactory;
use EasyUmsPay\Supports\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

test('可以创建默认HTTP客户端', function () {
    $config = new Config([]);

    $client = HttpClientFactory::createDefault($config);

    expect($client)->toBeInstanceOf(HttpClientInterface::class);
});

test('创建的客户端有正确的超时时间', function () {
    $config = new Config([
        'http' => [
            'timeout' => 15,
            'verify_host' => false,
        ],
    ]);

    $client = HttpClientFactory::createDefault($config);

    expect($client)->toBeInstanceOf(HttpClientInterface::class);
});

test('http.throw 不会传给 Symfony HttpClient', function () {
    $config = new Config([
        'http' => [
            'timeout' => 15,
            'throw' => false,
        ],
    ]);

    $client = HttpClientFactory::createDefault($config);

    expect($client)->toBeInstanceOf(HttpClientInterface::class);
});
