<?php

declare(strict_types=1);

namespace EasyUmsPay\Notify;

/**
 * 通知字段归一（两代购物车字段名不同）
 */
final class NotifyFields
{
    /**
     * @param  array<string, string>  $fields
     */
    public static function orderNo(array $fields): ?string
    {
        return self::first($fields, 'merOrderId', 'mchntOrderId');
    }

    /**
     * 主商户号：老购物车取 mid，新购物车优先 vaMchntNo
     *
     * @param  array<string, string>  $fields
     */
    public static function merchantNo(array $fields): ?string
    {
        return self::first($fields, 'vaMchntNo', 'mid');
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function terminalNo(array $fields): ?string
    {
        return self::first($fields, 'vaTermNo', 'tid');
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function amount(array $fields): ?int
    {
        $value = $fields['totalAmount'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function status(array $fields): ?string
    {
        return self::first($fields, 'status');
    }

    /**
     * 通知签名算法（以报文为准）
     *
     * @param  array<string, string>  $fields
     */
    public static function signType(array $fields): ?string
    {
        return self::first($fields, 'signType', 'sign_type');
    }

    /**
     * @param  array<string, string>  $fields
     */
    private static function first(array $fields, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $fields[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
