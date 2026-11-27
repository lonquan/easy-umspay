<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 人员性别
 * - 法人
 */
enum Gender: int
{
    case UNKNOWN = 0;           // 未知的性别
    case MALE = 1;              // 男性
    case FEMALE = 2;            // 女性
    case FEMALE_TO_MALE = 5;    // 女性改为男性
    case MALE_TO_FEMALE = 6;    // 男性改为女性
    case UNSPECIFIED = 9;       // 未说明的性别

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => '未知的性别',
            self::MALE => '男性',
            self::FEMALE => '女性',
            self::FEMALE_TO_MALE => '女性改为男性',
            self::MALE_TO_FEMALE => '男性改为女性',
            self::UNSPECIFIED => '未说明的性别',
        };
    }

    public function isMale(): bool
    {
        return in_array($this, [
            self::MALE,
            self::FEMALE_TO_MALE,
        ]);
    }

    public function isFemale(): bool
    {
        return in_array($this, [
            self::FEMALE,
            self::MALE_TO_FEMALE,
        ]);
    }

    public function isKnown(): bool
    {
        return ! in_array($this, [
            self::UNKNOWN,
            self::UNSPECIFIED,
        ]);
    }
}
