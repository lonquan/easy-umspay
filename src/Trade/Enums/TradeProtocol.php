<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Enums;

/**
 * 交易协议代际
 *
 * shopping_cart = 新购物车（/v1/inip/upsp/shop/*），netpay = 老购物车（/v1/netpay/*）。
 */
enum TradeProtocol: string
{
    case ShoppingCart = 'shopping_cart';
    case NetPay = 'netpay';

    public function label(): string
    {
        return match ($this) {
            self::ShoppingCart => '新购物车',
            self::NetPay => '老购物车',
        };
    }
}
