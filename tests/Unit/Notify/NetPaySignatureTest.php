<?php

declare(strict_types=1);

use EasyUmsPay\Notify\NetPaySignature;

/**
 * 官方「通知验签规则」示例报文（已排除 sign）
 *
 * @return array<string, string>
 */
function netPayNoticeFields(): array
{
    return [
        'Ue' => 'hobh',
        'billFunds' => '支付宝余额:1',
        'billFundsDesc' => '支付宝余额支付0.01元。',
        'buyerCashPayAmt' => '1',
        'buyerId' => '2088202932263863',
        'buyerPayAmount' => '1',
        'buyerUsername' => 'tj.***@gmail.com',
        'cardAttr' => 'BALANCE',
        'connectSys' => 'UNIONPAY',
        'couponAmount' => '0',
        'createTime' => '2022-11-16 09:48:12',
        'freeSettlementAmt' => '0',
        'instMid' => 'H5DEFAULT',
        'invoiceAmount' => '1',
        'mchntUuid' => '6d47dc12a4c847eaba2eb456d201d5cd',
        'merName' => '中保付测试商户(中保付测试商户)',
        'merOrderId' => '101720221116094809280000006',
        'mid' => '898201612345678',
        'msgType' => 'trade.notify',
        'notifyId' => '2bbcfd87-6e93-4fc4-b38f-1c5d853ecbb8',
        'orderDesc' => '中保付测试商户(中保付测试商户)',
        'payTime' => '2022-11-16 09:48:41',
        'receiptAmount' => '1',
        'seqId' => '01193401135N',
        'settleDate' => '2022-11-16',
        'signType' => 'SHA256',
        'status' => 'TRADE_SUCCESS',
        'subInst' => '100200',
        'targetOrderId' => '2022111622001463861446080716',
        'targetSys' => 'Alipay 2.0',
        'tid' => '88880001',
        'totalAmount' => '1',
    ];
}

test('官方示例 SHA256 验签通过', function () {
    $fields = netPayNoticeFields();
    $fields['sign'] = '68CFB169494DD0EA83DB11AA132A1C6EC46DED75F8E1DCAADDC0011D91B6B4FE';

    $strategy = new NetPaySignature;

    expect($strategy->verify(
        $fields,
        'impARTxrQcfwmRijpDNCw6hPxaWCddKEpYxjaKXDhCaTCXJ6',
        'sha256',
    ))->toBeTrue();
});

test('官方示例 MD5 验签通过', function () {
    $fields = netPayNoticeFields();
    $fields['sign'] = '7070E720EE550D9A60FB5B99B7201826';

    $strategy = new NetPaySignature;

    expect($strategy->verify(
        $fields,
        'impARTxrQcfwmRijpDNCw6hPxaWCddKEpYxjaKXDhCaTCXJ6',
        'md5',
    ))->toBeTrue();
});

test('字段被篡改时验签失败', function () {
    $fields = netPayNoticeFields();
    $fields['sign'] = '68CFB169494DD0EA83DB11AA132A1C6EC46DED75F8E1DCAADDC0011D91B6B4FE';
    $fields['totalAmount'] = '2';

    $strategy = new NetPaySignature;

    expect($strategy->verify(
        $fields,
        'impARTxrQcfwmRijpDNCw6hPxaWCddKEpYxjaKXDhCaTCXJ6',
        'sha256',
    ))->toBeFalse();
});

test('缺少签名或密钥时验签失败', function () {
    $strategy = new NetPaySignature;

    expect($strategy->verify([], 'key', 'sha256'))->toBeFalse()
        ->and($strategy->verify(['sign' => 'abc'], '', 'sha256'))->toBeFalse();
});
