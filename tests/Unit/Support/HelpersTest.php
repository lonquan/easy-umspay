<?php

declare(strict_types=1);

beforeEach(function () {
    // 清理环境变量
    putenv('TEST_STRING');
    putenv('TEST_TRUE');
    putenv('TEST_FALSE');
    putenv('TEST_NULL');
    putenv('TEST_EMPTY');
    putenv('TEST_QUOTED');
});

test('env函数返回字符串值', function () {
    $_ENV['TEST_STRING'] = 'hello world';

    expect(env('TEST_STRING'))->toBe('hello world');
});

test('env函数返回默认值', function () {
    expect(env('NON_EXISTENT_KEY', 'default'))->toBe('default');
});

test('env函数对布尔值true返回true', function () {
    $_ENV['TEST_TRUE'] = 'true';
    expect(env('TEST_TRUE'))->toBeTrue();

    $_ENV['TEST_TRUE'] = '(true)';
    expect(env('TEST_TRUE'))->toBeTrue();
});

test('env函数对布尔值false返回false', function () {
    $_ENV['TEST_FALSE'] = 'false';
    expect(env('TEST_FALSE'))->toBeFalse();

    $_ENV['TEST_FALSE'] = '(false)';
    expect(env('TEST_FALSE'))->toBeFalse();
});

test('env函数对null值返回null', function () {
    $_ENV['TEST_NULL'] = 'null';
    expect(env('TEST_NULL'))->toBeNull();

    $_ENV['TEST_NULL'] = '(null)';
    expect(env('TEST_NULL'))->toBeNull();
});

test('env函数对empty值返回空字符串', function () {
    $_ENV['TEST_EMPTY'] = 'empty';
    expect(env('TEST_EMPTY'))->toBe('');

    $_ENV['TEST_EMPTY'] = '(empty)';
    expect(env('TEST_EMPTY'))->toBe('');
});

test('env函数移除引号', function () {
    $_ENV['TEST_QUOTED'] = '"quoted value"';
    expect(env('TEST_QUOTED'))->toBe('quoted value');

    $_ENV['TEST_QUOTED'] = "'single quoted'";
    expect(env('TEST_QUOTED'))->toBe('single quoted');
});

test('env函数调用闭包获取默认值', function () {
    $result = env('NON_EXISTENT', fn () => 'from closure');
    expect($result)->toBe('from closure');
});

test('env函数优先使用ENV而非SERVER', function () {
    $_ENV['TEST_PRIORITY'] = 'from_env';
    $_SERVER['TEST_PRIORITY'] = 'from_server';

    expect(env('TEST_PRIORITY'))->toBe('from_env');

    unset($_ENV['TEST_PRIORITY']);
    expect(env('TEST_PRIORITY'))->toBe('from_server');
});
