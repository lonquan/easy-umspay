<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol\ShoppingCart;

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Exceptions\UnsupportedSceneException;

/**
 * 新购物车端点
 *
 * 官方文档：https://open.chinaums.com/saas-doc/openplate/netpay/mini-program/transaction/VxXa3w2o.html
 * 说明：官方当前只提供小程序（mini）与公众号（web）两套场景端点，H5/APP 无文档。
 */
final class Endpoints
{
    public static function pay(PayScene $scene): string
    {
        return match ($scene) {
            PayScene::WeChatMiniApp,
            PayScene::AliPayMiniApp,
            PayScene::UnionPayMiniApp,
            PayScene::UnionPayWxMiniApp => '/v1/inip/upsp/shop/mini/pay',

            PayScene::WeChatOfficialAccount,
            PayScene::AliPayOfficialAccount => '/v1/inip/upsp/shop/web/pay',

            default => throw self::unsupported($scene),
        };
    }

    public static function query(PayScene $scene): string
    {
        return match ($scene) {
            PayScene::WeChatMiniApp,
            PayScene::AliPayMiniApp,
            PayScene::UnionPayMiniApp,
            PayScene::UnionPayWxMiniApp => '/v1/inip/upsp/shop/mini/pay-query',

            PayScene::WeChatOfficialAccount,
            PayScene::AliPayOfficialAccount => '/v1/inip/upsp/shop/web/pay-query',

            default => throw self::unsupported($scene),
        };
    }

    public static function refund(PayScene $scene): string
    {
        return match ($scene) {
            PayScene::WeChatMiniApp,
            PayScene::AliPayMiniApp,
            PayScene::UnionPayMiniApp,
            PayScene::UnionPayWxMiniApp => '/v1/inip/upsp/shop/mini/refund',

            PayScene::WeChatOfficialAccount,
            PayScene::AliPayOfficialAccount => '/v1/inip/upsp/shop/web/refund',

            default => throw self::unsupported($scene),
        };
    }

    public static function refundQuery(PayScene $scene): string
    {
        return match ($scene) {
            PayScene::WeChatMiniApp,
            PayScene::AliPayMiniApp,
            PayScene::UnionPayMiniApp,
            PayScene::UnionPayWxMiniApp => '/v1/inip/upsp/shop/mini/refund-query',

            PayScene::WeChatOfficialAccount,
            PayScene::AliPayOfficialAccount => '/v1/inip/upsp/shop/web/refund-query',

            default => throw self::unsupported($scene),
        };
    }

    public static function close(PayScene $scene): string
    {
        self::pay($scene);

        return '/v1/inip/upsp/shop/common/order-close';
    }

    private static function unsupported(PayScene $scene): UnsupportedSceneException
    {
        return new UnsupportedSceneException(
            '新购物车暂不支持该支付场景: ' . $scene->value,
            'UNSUPPORTED_SCENE',
            ['scene' => $scene->value],
        );
    }
}
