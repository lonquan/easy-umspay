<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Enums;

/**
 * 渠道退款状态
 */
enum RefundStatus: string
{
    case PROCESSING = 'PROCESSING';
    case REFUND_SUCCESS = 'REFUND_SUCCESS';
    case REFUND_FAILED = 'REFUND_FAILED';

    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => '退款处理中',
            self::REFUND_SUCCESS => '退款成功',
            self::REFUND_FAILED => '退款失败',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::REFUND_SUCCESS;
    }
}
