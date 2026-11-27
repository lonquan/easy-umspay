<?php

declare(strict_types=1);

use EasyUmsPay\Crypto\TripleDESCrypto;
use EasyUmsPay\Exceptions\CryptoException;

/**
 * @property string $validKey
 */
beforeEach(function () {
    $this->validKey = '123456789012345678901234'; // 24 bytes
});

test('可以使用有效密钥创建实例', function () {
    $crypto = new TripleDESCrypto($this->validKey);

    expect($crypto)->toBeInstanceOf(TripleDESCrypto::class);
});

test('无效密钥长度时抛出异常', function () {
    expect(fn () => new TripleDESCrypto('short_key'))
        ->toThrow(CryptoException::class, '3DES key must be 24 bytes');
});

test('可以加密明文', function () {
    $crypto = new TripleDESCrypto($this->validKey);
    $plaintext = 'Hello, World!';

    $encrypted = $crypto->encrypt($plaintext);

    expect($encrypted)->toBeString()
        ->and($encrypted)->not->toBe($plaintext)
        ->and($encrypted)->not->toBeEmpty();
});

test('可以解密密文', function () {
    $crypto = new TripleDESCrypto($this->validKey);
    $plaintext = 'Hello, World!';

    $encrypted = $crypto->encrypt($plaintext);
    $decrypted = $crypto->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
});

test('加密解密中文字符往返测试', function () {
    $crypto = new TripleDESCrypto($this->validKey);
    $plaintext = '测试中文字符：银联支付';

    $encrypted = $crypto->encrypt($plaintext);
    $decrypted = $crypto->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
});

test('加密返回Base64编码的字符串', function () {
    $crypto = new TripleDESCrypto($this->validKey);
    $plaintext = 'test';

    $encrypted = $crypto->encrypt($plaintext);

    // Base64编码的字符串应该可以被base64_decode
    expect(base64_decode($encrypted, true))->not->toBeFalse();
});

test('解密处理无效密文时抛出异常', function () {
    $crypto = new TripleDESCrypto($this->validKey);

    expect(fn () => $crypto->decrypt('invalid_base64_string!!!'))
        ->toThrow(CryptoException::class, 'Decryption failed');
});

test('ECB模式产生确定性输出', function () {
    $crypto = new TripleDESCrypto($this->validKey);
    $plaintext = 'test123';

    // ECB 模式对相同的明文应该产生相同的密文
    $encrypted1 = $crypto->encrypt($plaintext);
    $encrypted2 = $crypto->encrypt($plaintext);

    expect($encrypted1)->toBe($encrypted2);
});
