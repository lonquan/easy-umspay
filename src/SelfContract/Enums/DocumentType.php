<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 可上传的图片类型
 */
enum DocumentType: string
{
    case ID_CARD_FRONT = '0001';             // 身份证人像面
    case ID_CARD_BACK = '0011';              // 身份证国徽面
    case BUSINESS_LICENSE = '0002';          // 商户营业执照
    case TAX_CERTIFICATE = '0003';           // 商户税务登记证
    case ORG_CODE_CERTIFICATE = '0004';      // 组织机构代码证
    case DOOR_PHOTO = '0005';                // 门头照片
    case COMPANY_BANK_MATERIAL = '0006';     // 对公结算材料
    case SELFIE = '0007';                    // 自拍照
    case SUPPORT_MATERIAL = '0008';          // 辅助证明材料
    case SUPPORT_MATERIAL_1 = '0013';        // 辅助证明材料1
    case SUPPORT_MATERIAL_2 = '0014';        // 辅助证明材料2
    case AUTHORIZATION_CERT = '0012';        // 授权证书
    case LEASE_CONTRACT = '0016';            // 租赁协议
    case PROPERTY_CERT = '0017';             // 产权证明
    case QUALIFICATION_CERT = '0018';        // 执业资质证照
    case THIRD_PARTY_PROOF = '0019';         // 第三方证明
    case MICRO_MERCHANT_PROOF = '0020';      // 其他小微商户证明材料
    case PRODUCT_PHOTO = '0021';             // 经营商品
    case INDOOR_PHOTO = '0022';              // 室内照片
    case BANK_CARD_FRONT = '0025';           // 银行卡正面照
    case BANK_CARD_BACK = '0026';            // 银行卡背面照
    case PRIVATE_SCHOOL_CERT = '0032';       // 民办非登记证书
    case ACCOUNT_MGMT_PROOF = '0033';        // 双方授权结算账户资金管理证明
    case WEBSITE_SCREENSHOT = '0034';        // 网站或APP截图
    case MANAGER_INSPECTION_PHOTO = '0040';  // 客户经理现场考察照
    case AUTHORIZATION_LETTER = '0044';      // 授权人授权书
    case AUTHORIZER_ID_FRONT = '0045';       // 授权人身份证人像面
    case AUTHORIZER_ID_BACK = '0046';        // 授权人身份证国徽面
    case OTHER = '0099';                     // 其他材料

    public function label(): string
    {
        return match ($this) {
            self::ID_CARD_FRONT => '身份证人像面',
            self::ID_CARD_BACK => '身份证国徽面',
            self::BUSINESS_LICENSE => '商户营业执照',
            self::TAX_CERTIFICATE => '商户税务登记证',
            self::ORG_CODE_CERTIFICATE => '组织机构代码证',
            self::DOOR_PHOTO => '门头照片',
            self::COMPANY_BANK_MATERIAL => '对公结算材料',
            self::SELFIE => '手持身份证自拍照',
            self::SUPPORT_MATERIAL => '辅助证明材料',
            self::SUPPORT_MATERIAL_1 => '辅助证明材料1',
            self::SUPPORT_MATERIAL_2 => '辅助证明材料2',
            self::AUTHORIZATION_CERT => '授权证书',
            self::LEASE_CONTRACT => '租赁协议',
            self::PROPERTY_CERT => '产权证明',
            self::QUALIFICATION_CERT => '执业资质证照',
            self::THIRD_PARTY_PROOF => '第三方证明',
            self::MICRO_MERCHANT_PROOF => '其他小微商户证明材料',
            self::PRODUCT_PHOTO => '经营商品',
            self::INDOOR_PHOTO => '室内照片',
            self::BANK_CARD_FRONT => '银行卡正面照',
            self::BANK_CARD_BACK => '银行卡背面照',
            self::PRIVATE_SCHOOL_CERT => '民办非登记证书',
            self::ACCOUNT_MGMT_PROOF => '授权结算账户资金管理证明',
            self::WEBSITE_SCREENSHOT => '网站或APP截图',
            self::MANAGER_INSPECTION_PHOTO => '客户经理现场考察照',
            self::AUTHORIZATION_LETTER => '授权人授权书',
            self::AUTHORIZER_ID_FRONT => '授权人身份证人像面',
            self::AUTHORIZER_ID_BACK => '授权人身份证国徽面',
            self::OTHER => '其他材料',
        };
    }

    public function isRequired(): bool
    {
        // 通用必传
        if (in_array($this, [
            self::ID_CARD_FRONT,
            self::ID_CARD_BACK,
            self::BUSINESS_LICENSE,
        ])) {
            return true;
        }

        // 条件必传：0016-0020 需至少一项
        if (in_array($this, [
            self::LEASE_CONTRACT,
            self::PROPERTY_CERT,
            self::QUALIFICATION_CERT,
            self::THIRD_PARTY_PROOF,
            self::MICRO_MERCHANT_PROOF,
        ])) {
            return true;
        }

        // 授权人相关文件（0044-0046），当存在授权人时必传
        if (in_array($this, [
            self::AUTHORIZATION_LETTER,
            self::AUTHORIZER_ID_FRONT,
            self::AUTHORIZER_ID_BACK,
        ])) {
            return true;
        }

        // 对公非同名账户需传 0033
        if ($this === self::ACCOUNT_MGMT_PROOF) {
            return true;
        }

        return false;
    }

    public function isOptional(): bool
    {
        // 选传材料，如辅助证明、网站截图、考察照、其他
        return in_array($this, [
            self::SUPPORT_MATERIAL,
            self::SUPPORT_MATERIAL_1,
            self::SUPPORT_MATERIAL_2,
            self::WEBSITE_SCREENSHOT,
            self::MANAGER_INSPECTION_PHOTO,
            self::OTHER,
        ]);
    }
}
