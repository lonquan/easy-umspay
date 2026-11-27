<?php

declare(strict_types=1);

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Protocol\NetPay\Endpoints;
use EasyUmsPay\Trade\Protocol\NetPay\Mapper;

/**
 * @return array<string, mixed>
 */
function npConfig(): array
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
function npOrder(array $overrides = []): array
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

test('老购物车小程序微信下单报文', function () {
    $payload = Mapper::pay(npOrder(), PayScene::WeChatMiniApp, npConfig());

    expect($payload)->toMatchArray([
        'merOrderId' => '1234ORDER0001',
        'mid' => 'M1000',
        'tid' => 'T0001',
        'totalAmount' => '100',
        'tradeType' => 'MINI',
        'instMid' => 'MINIDEFAULT',
        'subOpenId' => 'openid-1',
        'subAppId' => 'wx123',
        'msgId' => '1234ORDER0001',
        'srcReserve' => '1234ORDER0001',
        'msgSrc' => 'PAYHUB',
    ])->and($payload['requestTimestamp'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

test('老购物车云微小程序使用 UP_WX_MINI', function () {
    $payload = Mapper::pay(npOrder(), PayScene::UnionPayWxMiniApp, npConfig());

    expect($payload['tradeType'])->toBe('UP_WX_MINI')
        ->and(Endpoints::pay(PayScene::UnionPayWxMiniApp))->toBe('/v1/netpay/uac/mini-order');
});

test('老购物车 H5 不带 tradeType 但带 returnUrl', function () {
    $order = npOrder([
        'returnUrl' => 'https://example.com/return',
    ]);

    $payload = Mapper::pay($order, PayScene::WeChatH5, npConfig());

    expect($payload)->not->toHaveKey('tradeType')
        ->and($payload['returnUrl'])->toBe('https://example.com/return')
        ->and(Endpoints::pay(PayScene::WeChatH5))->toBe('/v1/netpay/wxpay/h5-pay');
});

test('老购物车退款与关单报文', function () {
    $refund = [
        'orderNo' => '1234ORDER0001',
        'refundOrderNo' => '1234REFUND0001',
        'amount' => 30,
    ];

    expect(Mapper::refund($refund, PayScene::WeChatMiniApp, npConfig()))->toMatchArray([
        'merOrderId' => '1234ORDER0001',
        'refundOrderId' => '1234REFUND0001',
        'refundAmount' => '30',
        'instMid' => 'MINIDEFAULT',
    ])->and(Mapper::close(npOrder(), PayScene::WeChatOfficialAccount, npConfig()))
        ->toMatchArray(['merOrderId' => '1234ORDER0001', 'instMid' => 'YUEDANDEFAULT']);
});

test('老购物车扩展字段透传并支持 instMid 覆盖', function () {
    $payload = Mapper::pay(
        npOrder([
            'instMid' => 'CUSTOM',
            'customField' => 'custom-value',
        ]),
        PayScene::WeChatMiniApp,
        npConfig(),
    );

    expect($payload['instMid'])->toBe('CUSTOM')
        ->and($payload['customField'])->toBe('custom-value');
});

test('老购物车端点表', function () {
    expect(Endpoints::pay(PayScene::WeChatMiniApp))->toBe('/v1/netpay/wx/unified-order')
        ->and(Endpoints::pay(PayScene::AliPayApp))->toBe('/v1/netpay/trade/precreate')
        ->and(Endpoints::pay(PayScene::UnionPayOfficialAccount))->toBe('/v1/netpay/acp/js-pay')
        ->and(Endpoints::query())->toBe('/v1/netpay/query')
        ->and(Endpoints::refund())->toBe('/v1/netpay/refund')
        ->and(Endpoints::refundQuery())->toBe('/v1/netpay/refund-query')
        ->and(Endpoints::close())->toBe('/v1/netpay/close');
});
