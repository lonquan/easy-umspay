<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * 渠道报文时间
 *
 * 银联商务两代购物车的报文时间统一为 Asia/Shanghai 的 `yyyy-MM-dd HH:mm:ss`，
 * 协议层与报文映射共用本类，避免各写一份格式化逻辑。
 */
final class Times
{
    /**
     * 渠道当前时间。
     */
    public static function now(): string
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
    }

    /**
     * 渠道时间格式化（任意时区入参统一转渠道时区）。
     */
    public static function format(DateTimeInterface $time): string
    {
        return DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new DateTimeZone('Asia/Shanghai'))
            ->format('Y-m-d H:i:s');
    }
}
