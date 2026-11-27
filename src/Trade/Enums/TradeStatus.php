<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Enums;

/**
 * 渠道交易状态（两代购物车取值一致）
 */
enum TradeStatus: string
{
    case NEW_ORDER = 'NEW_ORDER';
    case WAIT_BUYER_PAY = 'WAIT_BUYER_PAY';
    case TRADE_SUCCESS = 'TRADE_SUCCESS';
    case TRADE_CLOSED = 'TRADE_CLOSED';
    case TRADE_REFUND = 'TRADE_REFUND';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::NEW_ORDER => '新建订单',
            self::WAIT_BUYER_PAY => '待支付',
            self::TRADE_SUCCESS => '支付成功',
            self::TRADE_CLOSED => '已关闭',
            self::TRADE_REFUND => '已退款',
            self::UNKNOWN => '未知状态',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::TRADE_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this === self::WAIT_BUYER_PAY || $this === self::NEW_ORDER;
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::TRADE_SUCCESS,
            self::TRADE_CLOSED,
            self::TRADE_REFUND,
        ], true);
    }
}
