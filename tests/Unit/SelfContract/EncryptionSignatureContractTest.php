<?php

declare(strict_types=1);

use EasyUmsPay\Crypto\Sha256Signer;
use EasyUmsPay\Crypto\TripleDESCrypto;

const TEST_KEY = '123456789012345678901234';  // 24字节测试密钥（精确24字节）

test('签名和加密都使用明文', function () {
    $signer = new Sha256Signer;
    $crypto = new TripleDESCrypto(TEST_KEY);

    $plaintext = '{"service":"test","data":"value"}';

    // 1. 对明文签名
    $sign = $signer->sign($plaintext);

    // 2. 对明文加密（不是对签名加密）
    $encrypted = $crypto->encrypt($plaintext);

    // 3. 解密后应恢复原文（证明加密的是明文）
    $decrypted = $crypto->decrypt($encrypted);
    expect($decrypted)->toBe($plaintext);
});

test('签名是64字符十六进制', function () {
    $signer = new Sha256Signer;
    $sign = $signer->sign('test data');

    expect(strlen($sign))->toBe(64)
        ->and($sign)->toMatch('/^[a-fA-F0-9]{64}$/');
});

test('加密输出是Base64', function () {
    $crypto = new TripleDESCrypto(TEST_KEY);
    $encrypted = $crypto->encrypt('test data');

    // Base64 解码应成功
    $decoded = base64_decode($encrypted, true);
    expect($decoded)->not->toBeFalse();

    if ($decoded !== false) {
        expect($encrypted)->toBe(base64_encode($decoded));
    }
});

test('解密恢复原始明文', function () {
    $crypto = new TripleDESCrypto(TEST_KEY);

    $plaintext = '{"service":"pic_upload","data":"测试中文"}';
    $encrypted = $crypto->encrypt($plaintext);
    $decrypted = $crypto->decrypt($encrypted);

    expect($decrypted)->toBe($plaintext);
});

test('签名顺序独立于加密', function () {
    $signer = new Sha256Signer;
    $crypto = new TripleDESCrypto(TEST_KEY);

    $plaintext = '{"service":"test"}';

    // 方式1: 先签名后加密
    $sign1 = $signer->sign($plaintext);
    $encrypted1 = $crypto->encrypt($plaintext);

    // 方式2: 先加密后签名
    $encrypted2 = $crypto->encrypt($plaintext);
    $sign2 = $signer->sign($plaintext);

    // 结果应该相同（顺序无关）
    expect($sign1)->toBe($sign2)
        ->and($encrypted1)->toBe($encrypted2);
});

test('响应签名验证工作正常', function () {
    $signer = new Sha256Signer;

    // 模拟响应数据
    $responseData = [
        'errCode' => '00',
        'errMsg' => '成功',
        'pic_id' => 'PIC123',
        'pic_url' => 'https://example.com/pic.jpg',
    ];

    // 对响应数据签名（移除sign字段后按字段名排序）
    ksort($responseData);
    $signString = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $expectedSign = $signer->sign($signString);

    // 添加签名到响应
    $responseWithSign = $responseData;
    $responseWithSign['sign'] = $expectedSign;

    // 验证：重新计算签名应该匹配
    $dataForVerify = $responseWithSign;
    unset($dataForVerify['sign']);
    ksort($dataForVerify);
    $verifyString = json_encode($dataForVerify, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $actualSign = $signer->sign($verifyString);

    expect($expectedSign)->toBe($actualSign)
        ->and($signString)->toBe($verifyString);
});
