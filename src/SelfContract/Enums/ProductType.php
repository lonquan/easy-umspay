<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 可开通产品
 */
enum ProductType: string
{
    case UNIONPAY = '0';            // 银联卡（1）
    case QMF = '1';                 // 全民付（86）
    case POSTONG = '2';             // POS通（68）
    case MARKETING_UNION = '4';     // 营销联盟（40）
    case PUBLIC_PAY = '8';          // 公共支付-通用（9）
    case PAYOUT = '16';             // 代付业务（74）
    case MEMBER_CARD = '21';        // 统一会员卡（37）
    case APP_PAY = 'in1';           // APP支付（in1）
    case H5_PAY = 'in2';            // H5支付（in2）
    case WECHAT_MP_PAY = 'in3';     // 公众号支付（in3）
    case MINI_PROGRAM_PAY = 'in4';  // 小程序支付（in4）

    public function label(): string
    {
        return match ($this) {
            self::UNIONPAY => '银联卡',
            self::QMF => '全民付',
            self::POSTONG => 'POS通',
            self::MARKETING_UNION => '营销联盟',
            self::PUBLIC_PAY => '公共支付-通用',
            self::PAYOUT => '代付业务',
            self::MEMBER_CARD => '统一会员卡',
            self::APP_PAY => 'APP支付',
            self::H5_PAY => 'H5支付',
            self::WECHAT_MP_PAY => '公众号支付',
            self::MINI_PROGRAM_PAY => '小程序支付',
        };
    }

    public function isOnlinePayment(): bool
    {
        // 线上支付类产品
        return in_array($this, [
            self::APP_PAY,
            self::H5_PAY,
            self::WECHAT_MP_PAY,
            self::MINI_PROGRAM_PAY,
        ]);
    }

    public function isOfflinePayment(): bool
    {
        // 线下支付类产品
        return in_array($this, [
            self::UNIONPAY,
            self::QMF,
            self::POSTONG,
            self::MARKETING_UNION,
            self::PUBLIC_PAY,
            self::PAYOUT,
            self::MEMBER_CARD,
        ]);
    }
}
