<?php

declare(strict_types=1);

use EasyUmsPay\Auth\OpenBodySig;

test('生成官方格式的认证头', function () {
    $sig = new OpenBodySig('app-id', 'app-key');

    $header = $sig->header('{"a":1}', '20240101120000', 'nonce123');

    expect($header)->toStartWith('OPEN-BODY-SIG AppId="app-id",Timestamp="20240101120000",Nonce="nonce123",Signature="');
});

test('签名符合官方算法', function () {
    $sig = new OpenBodySig('app-id', 'app-key');
    $body = '{"a":1}';
    $plain = 'app-id' . '20240101120000' . 'nonce123' . hash('sha256', $body);
    $expected = base64_encode(hash_hmac('sha256', $plain, 'app-key', true));

    expect($sig->signature($body, '20240101120000', 'nonce123'))->toBe($expected);
});

test('默认时间戳与随机串长度符合要求', function () {
    $sig = new OpenBodySig('app-id', 'app-key');

    $header = $sig->header('{"a":1}');

    expect($header)->toMatch('/Timestamp="\d{14}"/')
        ->and($header)->toMatch('/Nonce="[0-9a-f]{32}"/');
});
