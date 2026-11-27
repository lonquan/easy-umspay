<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 是否连锁商户
 */
enum ChainMerchant: string
{
    case YES = '0';   // 是
    case NO = '1';    // 否

    public function label(): string
    {
        return match ($this) {
            self::YES => '是',
            self::NO => '否',
        };
    }

    public function isChain(): bool
    {
        return $this === self::YES;
    }

    public function isIndependent(): bool
    {
        return $this === self::NO;
    }
}
