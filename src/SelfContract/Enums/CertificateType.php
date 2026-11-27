<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 证件类型
 */
enum CertificateType: string
{
    case ID_CARD = '1';                    // 身份证
    case PASSPORT = '2';                   // 护照
    case MILITARY_ID = '3';                // 军官证
    case POLICE_ID = '4';                  // 警官证
    case SOLDIER_ID = '5';                 // 士兵证
    case TAIWAN_PASS = '6';                // 台湾居民来往大陆通行证
    case HOMETOWN_PASS = '7';              // 回乡证
    case HK_MACAO_PASS = '8';              // 港澳居民来往内地通行证
    case HK_MACAO_TW_RESIDENCE = '10';     // 港澳台居民居住证
    case BUSINESS_LICENSE = '11';          // 营业执照
    case ORG_CODE_CERT = '12';             // 组织机构代码证
    case TAX_REG_CERT = '13';              // 税务登记证
    case COMMERCIAL_REG_CERT = '14';       // 商业登记证
    case NON_ENTERPRISE_CERT = '15';       // 民办非企业登记证书
    case APPROVAL_DOC = '16';              // 批文证明

    public function label(): string
    {
        return match ($this) {
            self::ID_CARD => '身份证',
            self::PASSPORT => '护照',
            self::MILITARY_ID => '军官证',
            self::POLICE_ID => '警官证',
            self::SOLDIER_ID => '士兵证',
            self::TAIWAN_PASS => '台湾居民来往大陆通行证',
            self::HOMETOWN_PASS => '回乡证',
            self::HK_MACAO_PASS => '港澳居民来往内地通行证',
            self::HK_MACAO_TW_RESIDENCE => '港澳台居民居住证',
            self::BUSINESS_LICENSE => '营业执照',
            self::ORG_CODE_CERT => '组织机构代码证',
            self::TAX_REG_CERT => '税务登记证',
            self::COMMERCIAL_REG_CERT => '商业登记证',
            self::NON_ENTERPRISE_CERT => '民办非企业登记证书',
            self::APPROVAL_DOC => '批文证明',
        };
    }

    public function isPersonal(): bool
    {
        // 个人证件类型
        return in_array($this, [
            self::ID_CARD,
            self::PASSPORT,
            self::MILITARY_ID,
            self::POLICE_ID,
            self::SOLDIER_ID,
            self::TAIWAN_PASS,
            self::HOMETOWN_PASS,
            self::HK_MACAO_PASS,
            self::HK_MACAO_TW_RESIDENCE,
        ]);
    }

    public function isOrganization(): bool
    {
        // 机构或企业证件类型
        return in_array($this, [
            self::BUSINESS_LICENSE,
            self::ORG_CODE_CERT,
            self::TAX_REG_CERT,
            self::COMMERCIAL_REG_CERT,
            self::NON_ENTERPRISE_CERT,
            self::APPROVAL_DOC,
        ]);
    }
}
