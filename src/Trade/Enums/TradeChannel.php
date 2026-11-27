<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Enums;

/**
 * 交易渠道（用户实际付款的钱包）
 */
enum TradeChannel: string
{
    case WeChat = 'wechat';
    case AliPay = 'alipay';
    case UnionPay = 'unionpay';

    public function label(): string
    {
        return match ($this) {
            self::WeChat => '微信',
            self::AliPay => '支付宝',
            self::UnionPay => '云闪付',
        };
    }

    /**
     * 付款人标识在渠道报文中的字段名
     */
    public function payerField(): string
    {
        return match ($this) {
            self::AliPay => 'userId',
            default => 'subOpenId',
        };
    }
}
