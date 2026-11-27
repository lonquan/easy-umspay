<?php

declare(strict_types=1);

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Exceptions\UnsupportedSceneException;
use EasyUmsPay\Trade\Protocol\ShoppingCart\Endpoints;
use EasyUmsPay\Trade\Protocol\ShoppingCart\Mapper;

/**
 * @return array<string, mixed>
 */
function scConfig(): array
{
    return [
        'base_url' => 'https://test-api-open.chinaums.com',
        'app_id' => 'app-id',
        'app_key' => 'app-key',
        'merchant_no' => 'M1000',
        'terminal_no' => 'T0001',
        'message_source' => 'PAYHUB',
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scOrder(PayScene $scene, array $overrides = []): array
{
    return array_merge([
        'orderNo' => '1234ORDER0001',
        'amount' => 100,
        'description' => '测试商品',
        'notifyUrl' => 'https://example.com/notify',
        'payerId' => 'openid-1',
        'subAppId' => 'wx123',
    ], $overrides);
}

test('新购物车小程序下单报文', function () {
    $payload = Mapper::pay(scOrder(PayScene::WeChatMiniApp), PayScene::WeChatMiniApp, scConfig());

    expect($payload)->toMatchArray([
        'mchntOrderId' => '1234ORDER0001',
        'vaMchntNo' => 'M1000',
        'vaTermNo' => 'T0001',
        'instMid' => 'MINIDEFAULT',
        'msgType' => 'wx.unifiedOrder',
        'tradeType' => 'MINI',
        'totalAmount' => '100',
        'orderDesc' => '测试商品',
        'notifyUrl' => 'https://example.com/notify',
        'subOpenId' => 'openid-1',
        'subAppId' => 'wx123',
        'sysSource' => 'OPT',
        'msgSrc' => 'PAYHUB',
    ])->and($payload['vaFld'])->toBeInstanceOf(stdClass::class);
});

test('新购物车公众号支付宝使用 userId 与 JSAPI', function () {
    $payload = Mapper::pay(
        scOrder(PayScene::AliPayOfficialAccount, ['payerId' => 'buyer-1']),
        PayScene::AliPayOfficialAccount,
        scConfig(),
    );

    expect($payload['msgType'])->toBe('trade.create')
        ->and($payload['tradeType'])->toBe('JSAPI')
        ->and($payload['userId'])->toBe('buyer-1')
        ->and($payload['instMid'])->toBe('YUEDANDEFAULT');
});

test('新购物车扩展字段透传并支持 instMid 覆盖', function () {
    $payload = Mapper::pay(
        scOrder(PayScene::WeChatMiniApp, [
            'instMid' => 'CUSTOM',
            'customField' => 'custom-value',
        ]),
        PayScene::WeChatMiniApp,
        scConfig(),
    );

    expect($payload['instMid'])->toBe('CUSTOM')
        ->and($payload['customField'])->toBe('custom-value');
});

test('新购物车端点只覆盖小程序与公众号', function () {
    expect(Endpoints::pay(PayScene::WeChatMiniApp))->toBe('/v1/inip/upsp/shop/mini/pay')
        ->and(Endpoints::pay(PayScene::WeChatOfficialAccount))->toBe('/v1/inip/upsp/shop/web/pay')
        ->and(Endpoints::close(PayScene::WeChatMiniApp))->toBe('/v1/inip/upsp/shop/common/order-close');
});

test('新购物车不支持 H5 与 APP', function () {
    expect(fn () => Endpoints::pay(PayScene::WeChatH5))
        ->toThrow(UnsupportedSceneException::class);
});
