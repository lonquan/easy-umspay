<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 主从关系绑定结果
 */
enum BindResult: string
{
    case FAILED = '0';   // 绑定失败
    case SUCCESS = '1';  // 绑定成功

    public function label(): string
    {
        return match ($this) {
            self::FAILED => '绑定失败',
            self::SUCCESS => '绑定成功',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
