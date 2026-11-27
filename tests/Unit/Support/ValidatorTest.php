<?php

declare(strict_types=1);

use EasyUmsPay\Supports\Validator;

test('验证正确的身份证号码', function () {
    $validIdCard = '110101199001011234';

    $isValid = Validator::validateIdCard($validIdCard);

    expect($isValid)->toBeTrue();
});

test('验证以X结尾的身份证号码', function () {
    $validIdCard = '11010119900101123X';

    $isValid = Validator::validateIdCard($validIdCard);

    expect($isValid)->toBeTrue();
});

test('拒绝无效长度的身份证号码', function () {
    $invalidIdCard = '1101011990010';

    $isValid = Validator::validateIdCard($invalidIdCard);

    expect($isValid)->toBeFalse();
});

test('拒绝包含无效字符的身份证号码', function () {
    $invalidIdCard = '11010119900101abcd';

    $isValid = Validator::validateIdCard($invalidIdCard);

    expect($isValid)->toBeFalse();
});

test('验证正确的营业执照号码', function () {
    $validLicense = '91110000123456789X';

    $isValid = Validator::validateLicenseNo($validLicense);

    expect($isValid)->toBeTrue();
});

test('拒绝无效长度的营业执照号码', function () {
    $invalidLicense = '91110000123';

    $isValid = Validator::validateLicenseNo($invalidLicense);

    expect($isValid)->toBeFalse();
});

test('拒绝包含无效字符的营业执照号码', function () {
    $invalidLicense = '9110000012345678IO'; // I和O不允许

    $isValid = Validator::validateLicenseNo($invalidLicense);

    expect($isValid)->toBeFalse();
});

test('验证正确的手机号码', function () {
    $validPhone = '13800138000';

    $isValid = Validator::validatePhone($validPhone);

    expect($isValid)->toBeTrue();
});

test('验证各种手机号前缀', function () {
    $validPrefixes = ['130', '131', '145', '156', '166', '176', '186', '198', '199'];

    foreach ($validPrefixes as $prefix) {
        $phone = $prefix . '12345678';
        expect(Validator::validatePhone($phone))->toBeTrue("Failed for prefix: {$prefix}");
    }
});

test('拒绝无效长度的手机号码', function () {
    expect(Validator::validatePhone('138001380'))->toBeFalse();
    expect(Validator::validatePhone('138001380000'))->toBeFalse();
});

test('拒绝无效前缀的手机号码', function () {
    $invalidPhone = '12012345678'; // 12x prefix不存在

    $isValid = Validator::validatePhone($invalidPhone);

    expect($isValid)->toBeFalse();
});

test('验证16位银行卡号', function () {
    $validCard = '6222021234567890';

    $isValid = Validator::validateBankCard($validCard);

    expect($isValid)->toBeTrue();
});

test('验证19位银行卡号', function () {
    $validCard = '6222021234567890123';

    $isValid = Validator::validateBankCard($validCard);

    expect($isValid)->toBeTrue();
});

test('拒绝太短的银行卡号', function () {
    $invalidCard = '622202123456789'; // 15位

    $isValid = Validator::validateBankCard($invalidCard);

    expect($isValid)->toBeFalse();
});

test('拒绝太长的银行卡号', function () {
    $invalidCard = '62220212345678901234'; // 20位

    $isValid = Validator::validateBankCard($invalidCard);

    expect($isValid)->toBeFalse();
});

test('拒绝包含字母的银行卡号', function () {
    $invalidCard = '622202123456789X';

    $isValid = Validator::validateBankCard($invalidCard);

    expect($isValid)->toBeFalse();
});
