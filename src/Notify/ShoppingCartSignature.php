<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

/**
 * 新购物车通知验签
 *
 * 新购物车文档只给出 sign / signType 字段，未单列验签细则；两代通知字段同构，
 * 因此沿用老购物车官方规则（见 {@see AbstractSignature}）。联调拿到新购物车真实报文后，
 * 若官方规则不同，只需在此覆写 signedString()，不影响调用方。
 */
final class ShoppingCartSignature extends AbstractSignature
{
}
