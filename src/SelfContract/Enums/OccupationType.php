<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 人员职业
 * - 法人
 */
enum OccupationType: int
{
    case PROFESSIONAL_TECHNICAL = 0;     // 各类专业、技术人员
    case ADMINISTRATIVE_LEADER = 1;      // 国家机关、党群组织、企事业单位的负责人
    case OFFICE_STAFF = 2;               // 办事人员和有关人员
    case BUSINESS_STAFF = 3;             // 商业工作人员
    case SERVICE_STAFF = 4;              // 服务性工作人员
    case AGRICULTURE_WORKER = 5;         // 农林牧渔劳动者
    case MANUAL_LABOR = 6;               // 生产、运输及部分体力劳动者
    case OTHER = 7;                      // 不便分类的其他劳动者

    public function label(): string
    {
        return match ($this) {
            self::PROFESSIONAL_TECHNICAL => '各类专业、技术人员',
            self::ADMINISTRATIVE_LEADER => '国家机关、党群组织、企事业单位的负责人',
            self::OFFICE_STAFF => '办事人员和有关人员',
            self::BUSINESS_STAFF => '商业工作人员',
            self::SERVICE_STAFF => '服务性工作人员',
            self::AGRICULTURE_WORKER => '农林牧渔劳动者',
            self::MANUAL_LABOR => '生产、运输及部分体力劳动者',
            self::OTHER => '不便分类的其他劳动者',
        };
    }

    /**
     * 体力劳动者
     */
    public function isManualLabor(): bool
    {
        return in_array($this, [
            self::AGRICULTURE_WORKER,
            self::MANUAL_LABOR,
        ]);
    }

    /**
     * 脑力劳动者
     */
    public function isWhiteCollar(): bool
    {
        return in_array($this, [
            self::PROFESSIONAL_TECHNICAL,
            self::ADMINISTRATIVE_LEADER,
            self::OFFICE_STAFF,
            self::BUSINESS_STAFF,
            self::SERVICE_STAFF,
        ]);
    }
}
