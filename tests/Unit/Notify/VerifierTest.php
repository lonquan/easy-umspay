<?php

declare(strict_types=1);

use EasyUmsPay\Notify\Verifier;
use EasyUmsPay\Trade\Enums\TradeProtocol;

/**
 * @return array<string, string>
 */
function verifierFields(string $signType = 'SHA256'): array
{
    return [
        'merOrderId' => '101720221116094809280000006',
        'mid' => '898201612345678',
        'tid' => '88880001',
        'status' => 'TRADE_SUCCESS',
        'totalAmount' => '1',
        'signType' => $signType,
    ];
}

/**
 * @param  array<string, string>  $fields
 */
function signFields(array $fields, string $key, string $algorithm): string
{
    $payload = $fields;
    unset($payload['sign']);
    ksort($payload, SORT_STRING);

    $pairs = [];

    foreach ($payload as $name => $value) {
        $pairs[] = $name . '=' . $value;
    }

    return strtoupper(hash($algorithm, implode('&', $pairs) . $key));
}

test('按报文 signType 选择 SHA256 策略', function () {
    $fields = verifierFields('SHA256');
    $fields['sign'] = signFields($fields, 'notify-key', 'sha256');

    expect(Verifier::forProtocol(TradeProtocol::NetPay)->verify($fields, 'notify-key'))->toBeTrue();
});

test('按报文 signType 选择 MD5 策略', function () {
    $fields = verifierFields('MD5');
    $fields['sign'] = signFields($fields, 'notify-key', 'md5');

    expect(Verifier::forProtocol(TradeProtocol::NetPay)->verify($fields, 'notify-key'))->toBeTrue();
});

test('缺少 signType 或算法未知时拒绝', function () {
    $missing = verifierFields();
    unset($missing['signType']);
    $missing['sign'] = 'abc';

    $unknown = verifierFields('RSA');
    $unknown['sign'] = 'abc';

    $verifier = Verifier::forProtocol(TradeProtocol::NetPay);

    expect($verifier->verify($missing, 'notify-key'))->toBeFalse()
        ->and($verifier->verify($unknown, 'notify-key'))->toBeFalse();
});

test('新购物车策略可实例化', function () {
    expect(Verifier::forProtocol(TradeProtocol::ShoppingCart))->toBeInstanceOf(Verifier::class);
});
