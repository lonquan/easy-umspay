<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 可通过银联下载的数据
 */
enum DataType: string
{
    case Province = 'province';  // 省份数据
    case City = 'city';          // 城市数据
    case County = 'county';      // 区县数据
    case Mcc = 'mcc';            // 行业分类代码

    public function label(): string
    {
        return match ($this) {
            self::Province => '省份数据',
            self::City => '城市数据',
            self::County => '区县数据',
            self::Mcc => '行业分类代码（MCC）',
        };
    }
}
