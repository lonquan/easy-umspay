<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 商户类型
 * 选填参数，若未填，以平台总体配置为准
 */
enum MerchantCategory: string
{
    case PHYSICAL = '0';     // 实体商户
    case ONLINE = '1';       // 网络商户
    case HYBRID = '2';       // 实体兼线上商户

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL => '实体商户',
            self::ONLINE => '网络商户',
            self::HYBRID => '实体兼线上商户',
        };
    }

    public function isPhysical(): bool
    {
        return $this === self::PHYSICAL;
    }

    public function isOnline(): bool
    {
        return $this === self::ONLINE;
    }

    public function isHybrid(): bool
    {
        return $this === self::HYBRID;
    }
}
