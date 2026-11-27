<?php

declare(strict_types=1);

use EasyUmsPay\Crypto\HmacSha256Signer;

/**
 * @property string $secretKey
 * @property HmacSha256Signer $signer
 */
beforeEach(function () {
    $this->secretKey = 'test_secret_key_for_hmac';
    $this->signer = new HmacSha256Signer($this->secretKey);
});

test('可以使用密钥签名数据', function () {
    $data = 'test_data_to_sign';

    $signature = $this->signer->sign($data);

    expect($signature)->toBeString()
        ->and($signature)->not->toBeEmpty();
});

test('相同数据和密钥产生相同签名', function () {
    $data = 'consistent_data';

    $signature1 = $this->signer->sign($data);
    $signature2 = $this->signer->sign($data);

    expect($signature1)->toBe($signature2);
});

test('不同密钥产生不同签名', function () {
    $data = 'same_data';

    $signer1 = new HmacSha256Signer('key1');
    $signer2 = new HmacSha256Signer('key2');

    $signature1 = $signer1->sign($data);
    $signature2 = $signer2->sign($data);

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
    $invalidSignature = 'invalid_signature';

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

test('签名是Base64编码的', function () {
    $data = 'test';
    $signature = $this->signer->sign($data);

    // Base64编码的字符串应该可以被base64_decode
    $decoded = base64_decode($signature, true);
    expect($decoded)->not->toBeFalse()
        ->and($decoded)->not->toBeEmpty();
});
