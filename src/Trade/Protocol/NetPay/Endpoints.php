<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol\NetPay;

use EasyUmsPay\Trade\Enums\PayScene;

/**
 * 老购物车端点
 *
 * 官方文档：https://open.chinaums.com/saas-doc/openplate/netpay/mini-program/transaction/VxXa3w2o.html
 * 下单按「渠道 × 场景」分端点，查询/退款/退款查询/关单为统一端点。
 */
final class Endpoints
{
    public static function pay(PayScene $scene): string
    {
        return match ($scene) {
            PayScene::WeChatMiniApp,
            PayScene::WeChatOfficialAccount,
            PayScene::WeChatApp => '/v1/netpay/wx/unified-order',

            PayScene::WeChatH5 => '/v1/netpay/wxpay/h5-pay',

            PayScene::AliPayMiniApp,
            PayScene::AliPayOfficialAccount => '/v1/netpay/trade/create',

            PayScene::AliPayH5 => '/v1/netpay/trade/h5-pay',

            PayScene::AliPayApp => '/v1/netpay/trade/precreate',

            PayScene::UnionPayMiniApp,
            PayScene::UnionPayWxMiniApp => '/v1/netpay/uac/mini-order',

            PayScene::UnionPayOfficialAccount => '/v1/netpay/acp/js-pay',

            PayScene::UnionPayH5 => '/v1/netpay/uac/order',

            PayScene::UnionPayApp => '/v1/netpay/uac/app-order',
        };
    }

    public static function query(): string
    {
        return '/v1/netpay/query';
    }

    public static function refund(): string
    {
        return '/v1/netpay/refund';
    }

    public static function refundQuery(): string
    {
        return '/v1/netpay/refund-query';
    }

    public static function close(): string
    {
        return '/v1/netpay/close';
    }
}
