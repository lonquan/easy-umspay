<?php

declare(strict_types=1);

use EasyUmsPay\Crypto\Sha256Signer;

/**
 * @property Sha256Signer $signer
 */
beforeEach(function () {
    $this->signer = new Sha256Signer;
});

test('可以签名数据', function () {
    $data = 'test_data_to_sign';

    $signature = $this->signer->sign($data);

    expect($signature)->toBeString()
        ->and(strlen($signature))->toBe(64); // SHA256 produces 64 hex characters
});

test('相同数据产生相同签名', function () {
    $data = 'consistent_data';

    $signature1 = $this->signer->sign($data);
    $signature2 = $this->signer->sign($data);

    expect($signature1)->toBe($signature2);
});

test('不同数据产生不同签名', function () {
    $signature1 = $this->signer->sign('data1');
    $signature2 = $this->signer->sign('data2');

    expect($signature1)->not->toBe($signature2);
});

test('可以验证有效签名', function () {
    $data = 'test_data';
    $signature = $this->signer->sign($data);

    $isValid = $this->signer->verify($data, $signature);

    expect($isValid)->toBeTrue();
});

test('验证无效签名返回false', function () {
    $data = 'test_data';
    $invalidSignature = 'invalid_signature_hash';

    $isValid = $this->signer->verify($data, $invalidSignature);

    expect($isValid)->toBeFalse();
});

test('数据被修改后验证返回false', function () {
    $originalData = 'original_data';
    $signature = $this->signer->sign($originalData);

    $modifiedData = 'modified_data';
    $isValid = $this->signer->verify($modifiedData, $signature);

    expect($isValid)->toBeFalse();
});

test('签名是小写十六进制字符串', function () {
    $data = 'test';
    $signature = $this->signer->sign($data);

    // 签名应该是小写的十六进制字符串
    expect($signature)->toBe('9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08')
        ->and($signature)->toMatch('/^[a-f0-9]+$/');
});
