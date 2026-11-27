<?php

declare(strict_types=1);

if (! function_exists('env')) {
    /**
     * 获取环境变量的值
     *
     * @param  string  $key  环境变量名
     * @param  mixed  $default  默认值
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // 如果未找到环境变量，返回默认值
        if ($value === false) {
            return is_callable($default) ? $default() : $default;
        }

        // 处理特殊的布尔值和 null 值
        $normalizedValue = match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };

        // 如果已经转换过，直接返回
        if ($normalizedValue !== $value) {
            return $normalizedValue;
        }

        // 处理带引号的字符串 (去除首尾引号)
        if (preg_match('/\A(["\'])(.*)\\1\z/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }
}
