<?php

declare(strict_types=1);

namespace EasyUmsPay;

use EasyUmsPay\Exceptions\CryptoException;
use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\Http\HttpClientFactory;
use EasyUmsPay\Logger\LoggerFactory;
use EasyUmsPay\SelfContract\Client as SelfContractClient;
use EasyUmsPay\Supports\Config;
use EasyUmsPay\Trade\Enums\TradeProtocol;
use EasyUmsPay\Trade\Protocol\NetPay\NetPayProtocol;
use EasyUmsPay\Trade\Protocol\ShoppingCart\ShoppingCartProtocol;
use EasyUmsPay\Trade\TradeClient;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * EasyUmsPay 主客户端
 *
 * 提供统一入口：自助签约（selfContract）与交易（trade）。
 * 交易协议由配置 `trade.protocol` 决定（新购物车 / 老购物车），两代共用同一套认证与响应归一。
 *
 * 详细使用说明见：
 * - README.md
 * - docs/api-client.md
 * - docs/business-api.md
 */
class UmsPay
{
    const string VERSION = '1.0.0';

    protected Config $config;

    protected HttpClient $httpClient;

    protected ?LoggerInterface $logger = null;

    protected ?SelfContractClient $selfContractClient = null;

    protected ?TradeClient $tradeClient = null;

    /**
     * @param  Config|array<string, mixed>  $config  配置对象或配置数组
     * @param  LoggerInterface|null  $logger  PSR-3日志处理器
     * @param  HttpClientInterface|null  $http  Symfony HTTP 客户端（默认使用 Symfony HttpClient）
     *
     * @throws ValidationException
     */
    public function __construct(Config|array $config, ?LoggerInterface $logger = null, ?HttpClientInterface $http = null)
    {
        $this->config = is_array($config) ? new Config($config) : $config;

        if ($logger !== null) {
            $this->logger = $logger;
        } elseif ($this->config->isLogEnabled()) {
            $this->logger = LoggerFactory::createDefault($this->config);
        }

        $this->httpClient = new HttpClient(
            $http ?? HttpClientFactory::createDefault($this->config),
            $this->logger,
            $this->config->isHttpThrowEnabled(),
            $this->config->getHttpLogging(),
        );
    }

    /**
     * 获取底层 API Client。
     *
     * 返回的客户端复用当前超时、日志和 throw 行为，但不负责 UMS 的签名/加密。
     */
    public function getApiClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * 获取自助签约客户端
     *
     * 用于商户入网、信息变更等功能
     * 详见：docs/business-api.md#selfcontract
     *
     * @throws CryptoException
     */
    public function selfContract(): SelfContractClient
    {
        if ($this->selfContractClient === null) {
            $this->selfContractClient = new SelfContractClient(
                $this->config,
                $this->httpClient,
            );
        }

        return $this->selfContractClient;
    }

    /**
     * 获取交易客户端
     *
     * 协议由 trade.protocol 决定；返回门面只暴露下单/查询/退款/退款查询/关单。
     *
     * @throws ValidationException
     */
    public function trade(): TradeClient
    {
        if ($this->tradeClient === null) {
            $protocol = $this->config->getProtocol();

            if ($protocol === null) {
                throw new ValidationException('缺少交易配置 trade.protocol');
            }

            $tradeConfig = $this->config->getTrade();

            $this->tradeClient = new TradeClient(match ($protocol) {
                TradeProtocol::ShoppingCart => new ShoppingCartProtocol($tradeConfig, $this->httpClient),
                TradeProtocol::NetPay => new NetPayProtocol($tradeConfig, $this->httpClient),
            });
        }

        return $this->tradeClient;
    }

    /**
     * 设置HTTP客户端（运行时替换）
     */
    public function setHttpClient(HttpClientInterface $http): self
    {
        $this->httpClient = new HttpClient(
            $http,
            $this->logger,
            $this->config->isHttpThrowEnabled(),
            $this->config->getHttpLogging(),
        );

        // 清除已缓存的客户端实例，下次调用时重新创建
        $this->selfContractClient = null;
        $this->tradeClient = null;

        return $this;
    }

    /**
     * 获取配置对象
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * 获取日志处理器
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
