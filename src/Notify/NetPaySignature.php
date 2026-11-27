<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

/**
 * 老购物车通知验签
 *
 * 官方规则（https://open.chinaums.com/saas-doc/openplate/status/netpay/notice_sign_rules.html）：
 * 排除 sign 后按参数名 ASCII 升序拼成 `k=v&k=v...`，末尾追加通讯密钥，取 SHA256 / MD5 大写十六进制比对。
 */
final class NetPaySignature extends AbstractSignature
{
}
