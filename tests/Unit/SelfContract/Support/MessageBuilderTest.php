<?php

declare(strict_types=1);

use EasyUmsPay\SelfContract\Supports\MessageBuilder;
use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;

/**
 * @property MessageBuilder $builder
 */
beforeEach(function () {
    RequestSequenceGenerator::setGenerator(null);

    $generator = new RequestSequenceGenerator;
    $this->builder = new MessageBuilder($generator, 'TEST_ACCESSER_ID');
});

afterEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

test('添加所有必需字段', function () {
    $message = $this->builder->build('test_service', []);

    expect($message)->toHaveKeys(['service', 'accesser_id', 'sign_type', 'request_date', 'request_seq']);
});

test('正确设置服务名称', function () {
    $message = $this->builder->build('pic_upload', []);

    expect($message['service'])->toBe('pic_upload');
});

test('注入访问者ID', function () {
    $message = $this->builder->build('test_service', []);

    expect($message['accesser_id'])->toBe('TEST_ACCESSER_ID');
});

test('签名类型始终是SHA256', function () {
    $message = $this->builder->build('test_service', []);

    expect($message['sign_type'])->toBe('SHA-256');
});

test('请求日期格式正确', function () {
    $message = $this->builder->build('test_service', []);

    // yyyyMMddHHmmss 格式（14位数字）
    expect($message['request_date'])->toMatch('/^\d{14}$/');

    // 验证是当前时间（允许1秒误差）
    $expected = date('YmdHis');
    $actual = $message['request_date'];
    expect(abs((int) $actual - (int) $expected))->toBeLessThanOrEqual(1);
});

test('每次调用请求序列号唯一', function () {
    $message1 = $this->builder->build('test_service', []);
    $message2 = $this->builder->build('test_service', []);

    expect($message1['request_seq'])->not->toBe($message2['request_seq']);
});

test('业务数据合并到根级别', function () {
    $businessData = [
        'pic_type' => '01',
        'pic_base64' => 'base64data',
        'custom_field' => 'value',
    ];

    $message = $this->builder->build('pic_upload', $businessData);

    // 业务字段应该在根级别
    expect($message['pic_type'])->toBe('01')
        ->and($message['pic_base64'])->toBe('base64data')
        ->and($message['custom_field'])->toBe('value');
});

test('处理空业务数据', function () {
    $message = $this->builder->build('test_service', []);

    // 即使没有业务数据，必填字段也应存在
    expect($message)->toHaveCount(5); // 5个必填字段
});

test('使用自定义流水号生成器', function () {
    RequestSequenceGenerator::setGenerator(static fn (): string => 'CUSTOM-SEQ-001');

    $message = $this->builder->build('test_service', []);

    expect($message['request_seq'])->toBe('CUSTOM-SEQ-001');
});
