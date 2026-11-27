<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 进件状态
 */
enum EntryStatus: string
{
    case NOT_SUBMITTED = '0';   // 未进件
    case FAILED = '1';          // 进件失败
    case IN_PROGRESS = '2';     // 进行中
    case SUCCESS = '3';         // 进件成功

    public function label(): string
    {
        return match ($this) {
            self::NOT_SUBMITTED => '未进件',
            self::FAILED => '进件失败',
            self::IN_PROGRESS => '进行中',
            self::SUCCESS => '进件成功',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isPending(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isNotSubmitted(): bool
    {
        return $this === self::NOT_SUBMITTED;
    }
}
