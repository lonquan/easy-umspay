<?php

declare(strict_types=1);

use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;

beforeEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

afterEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

test('生成30字符字符串', function () {
    $generator = new RequestSequenceGenerator;
    $sequence = $generator->generate();

    expect($sequence)->toBeString()
        ->and(strlen($sequence))->toBe(30);
});

test('生成唯一序列', function () {
    $generator = new RequestSequenceGenerator;
    $sequences = [];

    for ($i = 0; $i < 100; $i++) {
        $sequence = $generator->generate();
        expect($sequences)->not->toContain($sequence);
        $sequences[] = $sequence;
    }
});

test('格式正确：日期时间-微秒-随机数', function () {
    $generator = new RequestSequenceGenerator;
    $sequence = $generator->generate();

    // 日期时间部分: 14位数字
    $datetime = substr($sequence, 0, 14);
    expect($datetime)->toMatch('/^\d{14}$/');

    // 微秒部分: 6位数字
    $microtime = substr($sequence, 14, 6);
    expect($microtime)->toMatch('/^\d{6}$/');

    // 随机数部分: 10位十六进制
    $random = substr($sequence, 20, 10);
    expect($random)->toMatch('/^[a-f0-9]{10}$/');
});

test('日期时间部分匹配当前时间', function () {
    $generator = new RequestSequenceGenerator;
    $sequence = $generator->generate();

    $datetime = substr($sequence, 0, 14);
    $expected = date('YmdHis');

    // 允许1秒误差（执行耗时）
    expect(abs((int) $datetime - (int) $expected))->toBeLessThanOrEqual(1);
});

test('微秒部分是有效的微秒值', function () {
    $generator = new RequestSequenceGenerator;
    $sequence = $generator->generate();

    $microtime = (int) substr($sequence, 14, 6);

    // 微秒范围: 0-999999
    expect($microtime)->toBeGreaterThanOrEqual(0)
        ->and($microtime)->toBeLessThan(1000000);
});

test('不同实例间无状态', function () {
    $gen1 = new RequestSequenceGenerator;
    $gen2 = new RequestSequenceGenerator;

    $seq1 = $gen1->generate();
    $seq2 = $gen2->generate();

    // 不同实例应生成不同序列（高概率）
    expect($seq1)->not->toBe($seq2);
});

test('处理高并发', function () {
    $generator = new RequestSequenceGenerator;
    $sequences = [];

    // 快速生成1000个
    for ($i = 0; $i < 1000; $i++) {
        $sequences[] = $generator->generate();
    }

    // 验证唯一性
    $unique = array_unique($sequences);
    expect($unique)->toHaveCount(1000, 'Found duplicate sequences');
});

test('微秒随时间变化', function () {
    $generator = new RequestSequenceGenerator;

    $seq1 = $generator->generate();
    usleep(100); // 等待100微秒
    $seq2 = $generator->generate();

    $micro1 = substr($seq1, 14, 6);
    $micro2 = substr($seq2, 14, 6);

    // 微秒部分应该不同（高概率）
    expect($micro1)->not->toBe($micro2);
});

test('可以设置自定义流水号生成器', function () {
    RequestSequenceGenerator::setGenerator(static fn (): string => 'CUSTOM-SEQ-001');

    expect((new RequestSequenceGenerator)->generate())->toBe('CUSTOM-SEQ-001');
});

test('设置 null 后恢复默认生成器', function () {
    RequestSequenceGenerator::setGenerator(static fn (): string => 'CUSTOM-SEQ-001');
    RequestSequenceGenerator::setGenerator(null);

    expect(strlen((new RequestSequenceGenerator)->generate()))->toBe(30);
});
