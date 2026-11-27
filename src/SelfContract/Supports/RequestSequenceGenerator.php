<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Supports;

use Closure;
use Random\RandomException;
use Throwable;

/**
 * 请求流水号生成器
 *
 * 默认生成格式: {datetime:14}{microtime:6}{random:10} = 30位
 * 示例: 20251002143000123456a1b2c3d4e5
 *
 * 可通过 setGenerator() 设置进程级自定义生成器；未设置时使用默认实现。
 * 默认实现无状态、微秒级精度、支持高并发。
 */
final class RequestSequenceGenerator
{
    /** @var (Closure(): string)|null */
    private static ?Closure $generator = null;

    /**
     * 设置进程级自定义流水号生成器
     *
     * @param  (callable(): string)|null  $generator  传 null 恢复默认实现
     */
    public static function setGenerator(?callable $generator): void
    {
        self::$generator = $generator === null ? null : Closure::fromCallable($generator);
    }

    /**
     * 生成唯一的请求流水号
     *
     * @return string 使用默认实现时为 30 位字符串
     *
     * @throws Throwable
     */
    public function generate(): string
    {
        if (self::$generator !== null) {
            return (self::$generator)();
        }

        return $this->generateDefault();
    }

    /**
     * 使用默认算法生成流水号
     *
     * @throws RandomException
     */
    private function generateDefault(): string
    {
        // 1. 日期时间: yyyyMMddHHmmss (14位)
        $datetime = date('YmdHis');

        // 2. 微秒时间戳: 取微秒部分 (6位)
        $microtime = sprintf('%06d', (int) (microtime(true) * 1000000) % 1000000);

        // 3. 随机数: 十六进制 (10位)
        $random = substr(bin2hex(random_bytes(5)), 0, 10);

        return $datetime . $microtime . $random;
    }
}
