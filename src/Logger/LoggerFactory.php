<?php

declare(strict_types=1);

namespace EasyUmsPay\Logger;

use EasyUmsPay\Supports\Config;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    /**
     * 创建默认的Monolog实例（从Config读取配置）
     */
    public static function createDefault(Config $config): LoggerInterface
    {
        $logConfig = $config->getLog() ?? [];
        $defaultChannel = $config->getLogDefaultChannel() ?? 'stderr';
        $channelConfig = $config->getLogChannel($defaultChannel);

        if ($channelConfig === null) {
            // 没有可用通道时降级到 stderr；stderr 默认值只在这里定义
            $channelConfig = [
                'driver' => 'stderr',
                'level' => 'info',
            ];
        }

        $channels = is_array($logConfig['channels'] ?? null) ? $logConfig['channels'] : [];

        return self::createFromConfig(
            $channelConfig,
            'ums-pay',
            $channels
        );
    }

    /**
     * 根据配置创建Logger实例
     *
     * @param  array<string, mixed>  $config  日志通道配置
     * @param  string  $name  Logger名称
     * @param  array<string, array<string, mixed>>|null  $allChannels  所有通道配置（用于stack类型）
     *
     * @throws \InvalidArgumentException
     */
    public static function createFromConfig(
        array $config,
        string $name = 'ums-pay',
        ?array $allChannels = null
    ): LoggerInterface {
        $driver = $config['driver'] ?? 'stderr';

        return match ($driver) {
            'single' => self::createSingleLogger($config, $name),
            'daily' => self::createDailyLogger($config, $name),
            'stderr' => self::createStderrLogger($config, $name),
            'stack' => self::createStackLogger($config, $name, $allChannels ?? []),
            default => throw new \InvalidArgumentException("Unsupported log driver: {$driver}"),
        };
    }

    /**
     * 创建单文件日志Logger
     *
     * @param  array<string, mixed>  $config
     */
    protected static function createSingleLogger(array $config, string $name): LoggerInterface
    {
        $logger = new Logger($name);
        $path = $config['path'] ?? getcwd() . '/logs/umspay.log';
        $level = self::convertLogLevel($config['level'] ?? 'info');
        $replacePlaceholders = $config['replace_placeholders'] ?? true;

        // 确保日志目录存在
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handler = new StreamHandler($path, $level);

        // 如果启用占位符替换，添加处理器
        if ($replacePlaceholders) {
            $handler->pushProcessor(new PsrLogMessageProcessor);
        }

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * 创建按日期分割的日志Logger
     *
     * @param  array<string, mixed>  $config
     */
    protected static function createDailyLogger(array $config, string $name): LoggerInterface
    {
        $logger = new Logger($name);
        $path = $config['path'] ?? getcwd() . '/logs/umspay.log';
        $level = self::convertLogLevel($config['level'] ?? 'info');
        $days = $config['days'] ?? 7;
        $replacePlaceholders = $config['replace_placeholders'] ?? true;

        // 确保日志目录存在
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handler = new RotatingFileHandler($path, $days, $level);

        // 如果启用占位符替换，添加处理器
        if ($replacePlaceholders) {
            $handler->pushProcessor(new PsrLogMessageProcessor);
        }

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * 创建标准错误输出Logger (stderr)
     * 用于控制台输出，输出到标准错误流
     *
     * @param  array<string, mixed>  $config
     */
    protected static function createStderrLogger(array $config, string $name): LoggerInterface
    {
        $logger = new Logger($name);
        $level = self::convertLogLevel($config['level'] ?? 'info');
        $replacePlaceholders = $config['replace_placeholders'] ?? true;

        $handler = new StreamHandler('php://stderr', $level);

        // 如果启用占位符替换，添加处理器
        if ($replacePlaceholders) {
            $handler->pushProcessor(new PsrLogMessageProcessor);
        }

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * 创建多通道组合Logger
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, array<string, mixed>>  $allChannels  所有通道配置
     *
     * @throws \InvalidArgumentException
     */
    protected static function createStackLogger(array $config, string $name, array $allChannels): LoggerInterface
    {
        $logger = new Logger($name);
        $channels = $config['channels'] ?? [];

        if (empty($channels)) {
            throw new \InvalidArgumentException('Stack logger requires at least one channel');
        }

        foreach ($channels as $channelName) {
            if (! isset($allChannels[$channelName])) {
                throw new \InvalidArgumentException("Channel '{$channelName}' not found in configuration");
            }

            $channelConfig = $allChannels[$channelName];
            $channelLogger = self::createFromConfig($channelConfig, $name, $allChannels);

            // 将子通道的所有Handler添加到当前logger
            if ($channelLogger instanceof Logger) {
                foreach ($channelLogger->getHandlers() as $handler) {
                    $logger->pushHandler($handler);
                }
            }
        }

        return $logger;
    }

    /**
     * 转换日志级别字符串为Monolog Level枚举
     *
     * @param  string  $level  日志级别字符串
     * @return Level Monolog日志级别枚举
     */
    protected static function convertLogLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'warning', 'warn' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            default => Level::Info,
        };
    }
}
