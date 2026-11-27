<?php

declare(strict_types=1);

use EasyUmsPay\Notify\ShoppingCartSignature;

/**
 * 官方「通知验签规则」示例报文（老购物车给出的同构报文，已排除 sign）
 *
 * @return array<string, string>
 */
function shoppingCartNoticeFields(): array
{
    return [
        'billFunds' => '支付宝余额:1',
        'buyerId' => '2088202932263863',
        'buyerPayAmount' => '1',
        'instMid' => 'H5DEFAULT',
        'merOrderId' => '101720221116094809280000006',
        'mid' => '898201612345678',
        'notifyId' => '2bbcfd87-6e93-4fc4-b38f-1c5d853ecbb8',
        'payTime' => '2022-11-16 09:48:41',
        'seqId' => '01193401135N',
        'signType' => 'SHA256',
        'status' => 'TRADE_SUCCESS',
        'targetOrderId' => '2022111622001463861446080716',
        'tid' => '88880001',
        'totalAmount' => '1',
        'vaMchntNo' => '103290070111417',
        'vaTermNo' => '12340511',
    ];
}

/**
 * @param  array<string, string>  $fields
 */
function shoppingCartSign(array $fields, string $key, string $algorithm, bool $withNames = true): string
{
    $payload = $fields;
    unset($payload['sign']);
    ksort($payload, SORT_STRING);

    $content = $withNames
        ? implode('&', array_map(
            static fn (string $name, string $value): string => $name . '=' . $value,
            array_keys($payload),
            array_values($payload),
        ))
        : implode('', array_values($payload));

    return strtoupper(hash($algorithm, $content . $key));
}

test('新购物车与老购物车共用同一套官方验签规则', function () {
    $fields = shoppingCartNoticeFields();
    $fields['sign'] = shoppingCartSign($fields, 'notify-key', 'sha256');

    expect((new ShoppingCartSignature)->verify($fields, 'notify-key', 'sha256'))->toBeTrue();
});

test('新购物车 MD5 验签同样按 k=v& 拼接', function () {
    $fields = shoppingCartNoticeFields();
    $fields['signType'] = 'MD5';
    $fields['sign'] = shoppingCartSign($fields, 'notify-key', 'md5');

    expect((new ShoppingCartSignature)->verify($fields, 'notify-key', 'md5'))->toBeTrue();
});

test('只拼接参数值的旧实现不能通过验签', function () {
    $fields = shoppingCartNoticeFields();
    $fields['sign'] = shoppingCartSign($fields, 'notify-key', 'sha256', withNames: false);

    expect((new ShoppingCartSignature)->verify($fields, 'notify-key', 'sha256'))->toBeFalse();
});

test('字段被篡改、缺少签名或密钥时验签失败', function () {
    $fields = shoppingCartNoticeFields();
    $fields['sign'] = shoppingCartSign($fields, 'notify-key', 'sha256');
    $fields['totalAmount'] = '2';

    $strategy = new ShoppingCartSignature;

    expect($strategy->verify($fields, 'notify-key', 'sha256'))->toBeFalse()
        ->and($strategy->verify([], 'key', 'sha256'))->toBeFalse()
        ->and($strategy->verify(['sign' => 'abc'], '', 'sha256'))->toBeFalse();
});
