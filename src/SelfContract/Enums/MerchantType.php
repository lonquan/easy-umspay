<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 商户注册类型
 */
enum MerchantType: string
{
    case Enterprise = '00';
    case Individual = '01';
    case SmallMicro = '02';
    case GovernmentOrSocial = '03';
    case NonEnterprise = '05';

    public function label(): string
    {
        return match ($this) {
            self::Enterprise => '企业商户',
            self::Individual => '个人工商户',
            self::SmallMicro => '小微商户',
            self::GovernmentOrSocial => '机关事业单位或社会团体',
            self::NonEnterprise => '民办非企业',
        };
    }
}
