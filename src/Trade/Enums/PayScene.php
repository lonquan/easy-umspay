<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Enums;

/**
 * 支付场景（客户端 + 渠道的具体组合）
 *
 * 场景是端点、tradeType 与默认 instMid 的推导依据；新增组合时只加 case 与映射，
 * 不改公开方法签名。
 */
enum PayScene: string
{
    case WeChatMiniApp = 'wechat_mini_app';
    case WeChatOfficialAccount = 'wechat_official_account';
    case WeChatH5 = 'wechat_h5';
    case WeChatApp = 'wechat_app';

    case AliPayMiniApp = 'alipay_mini_app';
    case AliPayOfficialAccount = 'alipay_official_account';
    case AliPayH5 = 'alipay_h5';
    case AliPayApp = 'alipay_app';

    case UnionPayMiniApp = 'unionpay_mini_app';
    case UnionPayWxMiniApp = 'unionpay_wx_mini_app';
    case UnionPayOfficialAccount = 'unionpay_official_account';
    case UnionPayH5 = 'unionpay_h5';
    case UnionPayApp = 'unionpay_app';

    public function channel(): TradeChannel
    {
        return match ($this) {
            self::WeChatMiniApp, self::WeChatOfficialAccount, self::WeChatH5, self::WeChatApp => TradeChannel::WeChat,
            self::AliPayMiniApp, self::AliPayOfficialAccount, self::AliPayH5, self::AliPayApp => TradeChannel::AliPay,
            default => TradeChannel::UnionPay,
        };
    }

    public function isMiniApp(): bool
    {
        return in_array($this, [
            self::WeChatMiniApp,
            self::AliPayMiniApp,
            self::UnionPayMiniApp,
            self::UnionPayWxMiniApp,
        ], true);
    }

    public function isOfficialAccount(): bool
    {
        return in_array($this, [
            self::WeChatOfficialAccount,
            self::AliPayOfficialAccount,
            self::UnionPayOfficialAccount,
        ], true);
    }

    public function isH5(): bool
    {
        return in_array($this, [self::WeChatH5, self::AliPayH5, self::UnionPayH5], true);
    }

    public function isApp(): bool
    {
        return in_array($this, [self::WeChatApp, self::AliPayApp, self::UnionPayApp], true);
    }

    /**
     * 老购物车默认业务类型（instMid）
     */
    public function defaultInstMid(): string
    {
        return match (true) {
            $this->isMiniApp() => 'MINIDEFAULT',
            $this->isOfficialAccount() => 'YUEDANDEFAULT',
            $this->isApp() => 'APPDEFAULT',
            default => 'H5DEFAULT',
        };
    }
}
