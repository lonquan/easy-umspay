<?php

declare(strict_types=1);

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\RefundStatus;
use EasyUmsPay\Trade\Enums\TradeStatus;
use EasyUmsPay\Trade\Support\Payload;

test('成功码兼容两代购物车并识别失败报文', function () {
    expect(Payload::normalize(['errCode' => 'SUCCESS'])['success'])->toBeTrue()
        ->and(Payload::normalize(['respCode' => '0000'])['success'])->toBeTrue()
        ->and(Payload::normalize([])['success'])->toBeTrue()
        ->and(Payload::normalize(['errCode' => '1001', 'errInfo' => '授权失败'])['success'])->toBeFalse()
        ->and(Payload::normalize(['errCode' => '1001', 'errInfo' => '授权失败'])['errorMessage'])->toBe('授权失败');
});

test('错误码与错误描述按别名优先级取值', function () {
    $result = Payload::normalize([
        'err_code' => 'ERR_X',
        'err_msg' => '下划线版错误',
    ]);

    expect($result['errorCode'])->toBe('ERR_X')
        ->and($result['errorMessage'])->toBe('下划线版错误')
        ->and(Payload::normalize(['errCode' => '1001', 'errInfo' => '首选'])['errorMessage'])->toBe('首选');
});

test('未受理时保留渠道错误码', function () {
    $result = Payload::normalize(['respCode' => '9001']);

    expect($result['success'])->toBeFalse()
        ->and($result['errorCode'])->toBe('9001');
});

test('商户订单号、流水号与渠道交易号映射', function () {
    $newCart = Payload::normalize([
        'mchntOrderId' => '1234ORDER0001',
        'seqId' => 'SEQ-1',
        'pltSsn' => 'PLT-1',
        'targetOrderId' => 'TGT-1',
    ]);
    $oldCart = Payload::normalize([
        'merOrderId' => '1234ORDER0001',
        'pltSsn' => 'PLT-2',
    ]);

    expect($newCart['orderNo'])->toBe('1234ORDER0001')
        ->and($newCart['seqId'])->toBe('SEQ-1')
        ->and($newCart['targetOrderId'])->toBe('TGT-1')
        ->and($oldCart['orderNo'])->toBe('1234ORDER0001')
        ->and($oldCart['seqId'])->toBe('PLT-2')
        ->and($oldCart['targetOrderId'])->toBeNull();
});

test('交易状态缺失与未知值的处理', function () {
    expect(Payload::normalize([])['status'])->toBeNull()
        ->and(Payload::normalize(['status' => 'TRADE_SUCCESS'])['status'])->toBe(TradeStatus::TRADE_SUCCESS)
        ->and(Payload::normalize(['status' => 'SOME_NEW_STATUS'])['status'])->toBeNull();
});

test('金额与支付时间解析', function () {
    $result = Payload::normalize([
        'totalAmount' => '100',
        'payTime' => '2026-11-02 08:00:00',
    ]);

    expect($result['amount'])->toBe(100)
        ->and($result['paidAt']?->format('Y-m-d H:i:s'))->toBe('2026-11-02 08:00:00')
        ->and(Payload::normalize(['totalAmount' => 'abc'])['amount'])->toBeNull()
        ->and(Payload::normalize(['payTime' => 'not-a-time'])['paidAt'])->toBeNull();
});

test('退款字段解析：退款单号只认 refundOrderId', function () {
    $result = Payload::normalize([
        'mchntOrderId' => '1234ORDER0001',
        'refundOrderId' => '1234REFUND0001',
        'refundTargetOrderId' => 'RT-1',
        'refundAmount' => '30',
        'refundStatus' => 'SUCCESS',
    ]);

    expect($result['refundOrderNo'])->toBe('1234REFUND0001')
        ->and($result['refundTargetOrderId'])->toBe('RT-1')
        ->and($result['refundStatus'])->toBe(RefundStatus::REFUND_SUCCESS);

    expect(Payload::normalize(['mchntOrderId' => '1234ORDER0001'])['refundOrderNo'])->toBeNull();
});

test('退款状态兼容官方写法与历史别名', function () {
    $cases = [
        'SUCCESS' => RefundStatus::REFUND_SUCCESS,
        'SUCESS' => RefundStatus::REFUND_SUCCESS,
        'REFUND_SUCCESS' => RefundStatus::REFUND_SUCCESS,
        'FAIL' => RefundStatus::REFUND_FAILED,
        'FAILED' => RefundStatus::REFUND_FAILED,
        'REFUND_FAILED' => RefundStatus::REFUND_FAILED,
        'PROCESSING' => RefundStatus::PROCESSING,
        'WHATEVER' => null,
    ];

    foreach ($cases as $value => $expected) {
        expect(Payload::normalize(['refundStatus' => $value])['refundStatus'])->toBe($expected);
    }
});

test('按场景提取调起支付参数', function () {
    $mini = Payload::normalize(['miniPayRequest' => ['paySign' => 'sign-mini']], PayScene::WeChatMiniApp);
    $official = Payload::normalize(['jsPayRequest' => '{"paySign":"sign-js"}'], PayScene::WeChatOfficialAccount);
    $app = Payload::normalize(['appPayRequest' => ['prepayId' => 'prepay-1']], PayScene::WeChatApp);
    $h5 = Payload::normalize(
        ['preUrl' => 'https://qr.chinaums.com/pay/', 'nextUrl' => 'abc'],
        PayScene::WeChatH5,
    );
    $h5Fallback = Payload::normalize(['h5PayUrl' => 'https://qr.chinaums.com/pay/abc'], PayScene::WeChatH5);

    expect($mini['payParams'])->toBe(['paySign' => 'sign-mini'])
        ->and($official['payParams'])->toBe(['paySign' => 'sign-js'])
        ->and($app['payParams'])->toBe(['prepayId' => 'prepay-1'])
        ->and($h5['payParams'])->toBe(['payUrl' => 'https://qr.chinaums.com/pay/abc'])
        ->and($h5Fallback['payParams'])->toBe(['payUrl' => 'https://qr.chinaums.com/pay/abc'])
        ->and(Payload::normalize(['miniPayRequest' => ['paySign' => 'x']])['payParams'])->toBe([]);
});

test('requireClosed 决定关单成功与错误码', function () {
    $closed = Payload::normalize(['respCode' => '0000', 'status' => 'TRADE_CLOSED'], requireClosed: true);
    $notClosed = Payload::normalize(['respCode' => '0000', 'status' => 'TRADE_SUCCESS'], requireClosed: true);

    expect($closed['success'])->toBeTrue()
        ->and($closed['errorCode'])->toBe('')
        ->and($notClosed['success'])->toBeFalse()
        ->and($notClosed['errorCode'])->toBe('CLOSE_UNCONFIRMED')
        ->and($notClosed['errorMessage'])->toBe('渠道未返回关闭结果');
});

test('raw 保留原始报文', function () {
    $raw = ['respCode' => '0000', 'custom' => 'value'];

    expect(Payload::normalize($raw)['raw'])->toBe($raw);
});
