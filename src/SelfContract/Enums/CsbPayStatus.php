<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * C扫B多应用商户号支付可用状态
 */
enum CsbPayStatus: string
{
    case ENABLED = '00';                 // 可支付
    case SYNC_FAILED = '01';             // 不可支付，商户信息同步失败
    case NO_RESPONSE = '02';             // 网付平台接口未响应
    case APPLY_NOT_SUCCESS = '03';       // apply_status 非 03 入网成功状态
    case NO_MCH_ID = '04';               // 未开通 C扫B 多应用商户号或 B扫C 商户号
    case SYNC_IN_PROGRESS = '05';        // 不可支付，T表同步中（30分钟内）
    case NOT_FOUND = '06';               // 网付接口查询不到此商户号

    public function label(): string
    {
        return match ($this) {
            self::ENABLED => '可支付',
            self::SYNC_FAILED => '不可支付，商户信息同步失败',
            self::NO_RESPONSE => '网付平台接口未响应',
            self::APPLY_NOT_SUCCESS => '不可支付，入网状态非成功',
            self::NO_MCH_ID => '未开通C扫B多应用商户号或B扫C商户号',
            self::SYNC_IN_PROGRESS => '不可支付，T表同步中（30分钟内）',
            self::NOT_FOUND => '网付接口查询不到此商户号',
        };
    }

    public function isPayable(): bool
    {
        return $this === self::ENABLED;
    }

    public function isTemporary(): bool
    {
        // 可恢复性中间状态
        return in_array($this, [
            self::SYNC_IN_PROGRESS,
            self::NO_RESPONSE,
        ]);
    }

    public function isFailure(): bool
    {
        // 明确不可支付状态
        return in_array($this, [
            self::SYNC_FAILED,
            self::APPLY_NOT_SUCCESS,
            self::NO_MCH_ID,
            self::NOT_FOUND,
        ]);
    }
}
