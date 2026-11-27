<?php

declare(strict_types=1);

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Exceptions\UnsupportedSceneException;
use EasyUmsPay\UmsPay;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @param  list<string>  $responses
 * @param  list<array<string, mixed>>  $captured
 * @param  array<string, mixed>  $tradeOverrides
 */
function umsTradeClientForTest(array $responses, array &$captured = [], array $tradeOverrides = []): UmsPay
{
    $queue = $responses;
    $captured = [];

    $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue, &$captured): MockResponse {
        $captured[] = [
            'method' => $method,
            'url' => $url,
            'body' => is_string($options['body'] ?? null) ? $options['body'] : '',
            'headers' => $options['normalized_headers'] ?? [],
        ];

        $next = array_shift($queue);

        return new MockResponse($next ?? '{}');
    });

    return new UmsPay([
        'trade' => array_merge([
            'protocol' => 'shopping_cart',
            'base_url' => 'https://test-api-open.chinaums.com',
            'app_id' => 'app-id',
            'app_key' => 'app-key',
            'merchant_no' => 'M1000',
            'terminal_no' => 'T0001',
        ], $tradeOverrides),
    ], new NullLogger, $http);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tradePayData(array $overrides = []): array
{
    return array_merge([
        'orderNo' => '1234ORDER0001',
        'amount' => 100,
        'description' => '测试商品',
        'notifyUrl' => 'https://example.com/notify',
        'payerId' => 'openid-1',
    ], $overrides);
}

test('新购物车下单请求路径与认证头正确', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"respCode":"0000","merOrderId":"1234ORDER0001","seqId":"SEQ1","targetOrderId":"TGT1","status":"NEW_ORDER","miniPayRequest":{"appId":"wx","timeStamp":"1","nonceStr":"n","package":"p","signType":"RSA","paySign":"s"}}',
    ], $captured);

    $result = $client->trade()->pay(tradePayData(), PayScene::WeChatMiniApp);
    $first = $captured[0] ?? [];

    expect($result['success'])->toBeTrue()
        ->and($result['seqId'])->toBe('SEQ1')
        ->and($result['payParams'])->toHaveKey('paySign')
        ->and($first['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/inip/upsp/shop/mini/pay')
        ->and($first['body'] ?? '')->toContain('"mchntOrderId":"1234ORDER0001"')
        ->and(json_encode($first['headers'] ?? []))->toContain('OPEN-BODY-SIG');
});

test('新购物车查询与退款路径正确', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"respCode":"0000","merOrderId":"1234ORDER0001","status":"TRADE_SUCCESS","totalAmount":"100","payTime":"2024-01-01 12:00:00","seqId":"SEQ1","targetOrderId":"TGT1"}',
        '{"respCode":"0000","refundOrderId":"1234REFUND0001","refundStatus":"SUCCESS","refundTargetOrderId":"RT1"}',
    ], $captured);

    $query = $client->trade()->query('1234ORDER0001', PayScene::WeChatMiniApp);
    $refund = $client->trade()->refund([
        'orderNo' => '1234ORDER0001',
        'refundOrderNo' => '1234REFUND0001',
        'amount' => 30,
    ], PayScene::WeChatMiniApp);

    $first = $captured[0] ?? [];
    $second = $captured[1] ?? [];

    expect($query['success'])->toBeTrue()
        ->and($query['amount'])->toBe(100)
        ->and($query['paidAt'])->not->toBeNull()
        ->and($refund['success'])->toBeTrue()
        ->and($refund['refundTargetOrderId'])->toBe('RT1')
        ->and($first['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/inip/upsp/shop/mini/pay-query')
        ->and($second['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/inip/upsp/shop/mini/refund')
        ->and($second['body'] ?? '')->toContain('"refundOrderId":"1234REFUND0001"');
});

test('退款回执只按 refundOrderId 回显，不把原交易单号当退款单号', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"respCode":"0000","mchntOrderId":"1234ORDER0001","refundStatus":"SUCCESS"}',
    ], $captured);

    $refund = $client->trade()->refund([
        'orderNo' => '1234ORDER0001',
        'refundOrderNo' => '1234REFUND0001',
        'amount' => 30,
    ], PayScene::WeChatMiniApp);

    expect($refund['success'])->toBeTrue()
        ->and($refund['refundOrderNo'])->toBe('1234REFUND0001');
});

test('老购物车下单走 netpay 端点并兼容 errCode', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"errCode":"SUCCESS","merOrderId":"1234ORDER0001","seqId":"SEQ9","targetOrderId":"TGT9","status":"WAIT_BUYER_PAY","miniPayRequest":{"appId":"wx","timeStamp":"1","nonceStr":"n","package":"p","signType":"RSA","paySign":"s"}}',
    ], $captured, ['protocol' => 'netpay']);

    $result = $client->trade()->pay(tradePayData(), PayScene::WeChatMiniApp);
    $first = $captured[0] ?? [];

    expect($result['success'])->toBeTrue()
        ->and($first['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/netpay/wx/unified-order')
        ->and($first['body'] ?? '')->toContain('"mid":"M1000"')
        ->and($first['body'] ?? '')->toContain('"tid":"T0001"');
});

test('老购物车关单与退款查询使用统一端点', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"errCode":"SUCCESS","merOrderId":"1234ORDER0001","status":"TRADE_CLOSED","seqId":"SEQ9"}',
        '{"errCode":"SUCCESS","refundOrderId":"1234REFUND0001","refundStatus":"SUCCESS"}',
    ], $captured, ['protocol' => 'netpay']);

    $close = $client->trade()->close('1234ORDER0001', PayScene::WeChatOfficialAccount);
    $refund = $client->trade()->refundQuery('1234REFUND0001', PayScene::WeChatOfficialAccount);

    $first = $captured[0] ?? [];
    $second = $captured[1] ?? [];

    expect($close['success'])->toBeTrue()
        ->and($refund['success'])->toBeTrue()
        ->and($first['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/netpay/close')
        ->and($second['url'] ?? null)->toBe('https://test-api-open.chinaums.com/v1/netpay/refund-query');
});

test('任意扩展字段进入两代报文并可覆盖同名字段', function () {
    $captured = [];
    $client = umsTradeClientForTest([
        '{"respCode":"0000","merOrderId":"1234ORDER0001","status":"NEW_ORDER"}',
    ], $captured);

    $client->trade()->pay(tradePayData([
        'customField' => 'custom-value',
        'mchntOrderId' => 'CUSTOM-ID',
    ]), PayScene::WeChatMiniApp);

    $body = $captured[0]['body'] ?? '';

    expect($body)->toContain('"customField":"custom-value"')
        ->and($body)->toContain('"mchntOrderId":"CUSTOM-ID"');
});

test('缺少必填字段不提前校验，仍然发送请求', function () {
    $captured = [];
    $client = umsTradeClientForTest(['{"respCode":"0000"}'], $captured);

    $result = $client->trade()->pay([], PayScene::WeChatMiniApp);

    expect($captured)->toHaveCount(1)
        ->and($result['success'])->toBeTrue();
});

test('新购物车拒绝 H5 场景', function () {
    $captured = [];
    $client = umsTradeClientForTest(['{"respCode":"0000"}']);

    expect(fn () => $client->trade()->pay(tradePayData(), PayScene::WeChatH5))
        ->toThrow(UnsupportedSceneException::class);
});

test('渠道拒绝时返回失败结果', function () {
    $captured = [];
    $client = umsTradeClientForTest(['{"respCode":"1001","respDesc":"商户不存在"}']);

    $result = $client->trade()->pay(tradePayData(), PayScene::WeChatMiniApp);

    expect($result['success'])->toBeFalse()
        ->and($result['errorCode'])->toBe('1001')
        ->and($result['errorMessage'])->toBe('商户不存在');
});
