<?php

declare(strict_types=1);

use EasyUmsPay\SelfContract\Enums\ApplyStatus;

test('有所有状态值', function () {
    expect(ApplyStatus::SIGNING->value)->toBe('00')
        ->and(ApplyStatus::SIGN_SUCCESS->value)->toBe('01')
        ->and(ApplyStatus::AUDITING->value)->toBe('02')
        ->and(ApplyStatus::SUCCESS->value)->toBe('03')
        ->and(ApplyStatus::FAILED->value)->toBe('04')
        ->and(ApplyStatus::COMPANY_VERIFY->value)->toBe('05')
        ->and(ApplyStatus::RISK_AUDIT->value)->toBe('06')
        ->and(ApplyStatus::FILLING->value)->toBe('18')
        ->and(ApplyStatus::FROZEN->value)->toBe('31');
});

test('成功状态返回true', function () {
    expect(ApplyStatus::SUCCESS->isSuccess())->toBeTrue()
        ->and(ApplyStatus::FAILED->isSuccess())->toBeFalse();
});

test('失败状态返回true', function () {
    expect(ApplyStatus::FAILED->isFailed())->toBeTrue()
        ->and(ApplyStatus::SUCCESS->isFailed())->toBeFalse();
});

test('待处理状态返回true', function () {
    expect(ApplyStatus::SIGNING->isPending())->toBeTrue()
        ->and(ApplyStatus::AUDITING->isPending())->toBeTrue()
        ->and(ApplyStatus::COMPANY_VERIFY->isPending())->toBeTrue()
        ->and(ApplyStatus::SUCCESS->isPending())->toBeFalse()
        ->and(ApplyStatus::FAILED->isPending())->toBeFalse();
});

test('标签返回正确文本', function () {
    expect(ApplyStatus::SIGNING->label())->toBe('签约中')
        ->and(ApplyStatus::SUCCESS->label())->toBe('入网成功')
        ->and(ApplyStatus::FAILED->label())->toBe('入网失败');
});
