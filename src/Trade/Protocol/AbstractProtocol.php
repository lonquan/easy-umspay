<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol;

use EasyUmsPay\Auth\OpenBodySig;
use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Http\HttpClient;
use Random\RandomException;
use Throwable;

/**
 * 交易协议基类
 *
 * 只负责认证、发送与 JSON 解码；端点和报文字段由具体协议实现。
 */
abstract readonly class AbstractProtocol implements ProtocolInterface
{
    protected OpenBodySig $signer;

    protected string $baseUrl;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config  交易配置
     */
    public function __construct(
        array $config,
        protected HttpClient $http,
    ) {
        $this->config = $config;
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->signer = new OpenBodySig(
            (string) ($config['app_id'] ?? ''),
            (string) ($config['app_key'] ?? ''),
        );
    }

    /**
     * 发送交易请求并解码响应。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws RandomException
     * @throws Throwable
     */
    protected function post(string $uri, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new RequestException('交易报文体 JSON 编码失败', null, ['uri' => $uri]);
        }

        $response = $this->http->post($this->baseUrl . $uri, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->signer->header($body),
            ],
        ]);

        $content = $response->getContent(false);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RequestException('UMS 响应不是合法 JSON', null, [
                'uri' => $uri,
                'content' => $content,
            ]);
        }

        return $decoded;
    }
}
