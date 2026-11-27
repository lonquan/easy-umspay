<?php

declare(strict_types=1);

namespace EasyUmsPay\Supports;

use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Http\HttpLogging;
use EasyUmsPay\Trade\Enums\TradeProtocol;

use function array_key_exists;
use function is_string;
use function sprintf;
use function trim;

readonly class Config
{
    /** @var array<string, mixed>|null */
    public ?array $log;

    /** @var array<string, mixed> */
    public array $http;

    /** @var array<string, mixed> */
    public array $selfContract;

    /** @var array<string, mixed> */
    public array $trade;

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ValidationException
     */
    public function __construct(array $config)
    {
        $this->log = $config['log'] ?? null;

        $this->http = $config['http'] ?? [];
        $this->selfContract = $config['self_contract'] ?? [];
        $this->trade = $config['trade'] ?? [];

        if (array_key_exists('self_contract', $config)) {
            $this->validateModuleConfig(
                'self_contract',
                $this->selfContract,
                ['base_url', 'accesser_id', 'sign_key'],
            );
        }

        if (array_key_exists('trade', $config)) {
            $this->validateModuleConfig(
                'trade',
                $this->trade,
                ['protocol', 'base_url', 'app_id', 'app_key', 'merchant_no', 'terminal_no'],
            );

            if ($this->getProtocol() === null) {
                throw new ValidationException('缺少配置项或取值非法: trade.protocol 只支持 shopping_cart / netpay');
            }
        }
    }

    /**
     * 获取日志配置
     *
     * @return array<string, mixed>|null
     */
    public function getLog(): ?array
    {
        return $this->log;
    }

    /**
     * 是否启用日志
     */
    public function isLogEnabled(): bool
    {
        return $this->log !== null && (bool) ($this->log['enabled'] ?? false);
    }

    /**
     * 获取默认日志通道名称
     */
    public function getLogDefaultChannel(): ?string
    {
        $default = $this->log['default'] ?? null;

        return is_string($default) ? $default : null;
    }

    /**
     * 获取指定日志通道的配置
     *
     * @return array<string, mixed>|null
     */
    public function getLogChannel(string $channel): ?array
    {
        $channels = $this->log['channels'] ?? null;
        $channelConfig = is_array($channels) ? ($channels[$channel] ?? null) : null;

        return is_array($channelConfig) ? $channelConfig : null;
    }

    /**
     * 获取HTTP客户端配置
     *
     * @return array<string, mixed>
     */
    public function getHttp(): array
    {
        return $this->http;
    }

    /**
     * 获取 HTTP 请求/响应日志开关。
     */
    public function getHttpLogging(): HttpLogging
    {
        $logs = $this->log['http'] ?? null;
        $logs = is_array($logs) ? $logs : [];

        return new HttpLogging(
            request: (bool) ($logs['request'] ?? false),
            response: (bool) ($logs['response'] ?? false),
            body: (bool) ($logs['body'] ?? false),
        );
    }

    /**
     * 是否在访问响应时抛出 HTTP 异常。
     */
    public function isHttpThrowEnabled(): bool
    {
        return (bool) ($this->http['throw'] ?? true);
    }

    /**
     * 获取自助签约配置
     *
     * @return array<string, mixed>
     */
    public function getSelfContract(): array
    {
        return $this->selfContract;
    }

    /**
     * 获取交易配置
     *
     * @return array<string, mixed>
     */
    public function getTrade(): array
    {
        return $this->trade;
    }

    /**
     * 获取交易协议代际
     */
    public function getProtocol(): ?TradeProtocol
    {
        $protocol = $this->trade['protocol'] ?? null;

        return is_string($protocol) ? TradeProtocol::tryFrom($protocol) : null;
    }

    /**
     * @param  array<string, mixed>  $moduleConfig
     * @param  array<int, string>  $requiredKeys
     *
     * @throws ValidationException
     */
    protected function validateModuleConfig(string $module, array $moduleConfig, array $requiredKeys): void
    {
        foreach ($requiredKeys as $requiredKey) {
            $value = $moduleConfig[$requiredKey] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new ValidationException(sprintf('缺少配置项: %s.%s', $module, $requiredKey));
            }
        }
    }
}
