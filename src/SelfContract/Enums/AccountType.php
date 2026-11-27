<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 结算账户类型
 */
enum AccountType: string
{
    case PERSONAL = '0';   // 个人账户
    case COMPANY = '1';    // 公司账户

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => '个人账户',
            self::COMPANY => '公司账户',
        };
    }

    public function isPersonal(): bool
    {
        return $this === self::PERSONAL;
    }

    public function isCompany(): bool
    {
        return $this === self::COMPANY;
    }
}
