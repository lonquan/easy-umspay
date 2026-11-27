<?php

declare(strict_types=1);

namespace EasyUmsPay\Http;

use EasyUmsPay\Supports\Config;
use EasyUmsPay\UmsPay;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

use const PHP_VERSION;

class HttpClientFactory
{
    /** @var array<string, mixed> */
    protected const array DEFAULT_OPTIONS = [
        'timeout' => 10,
        'max_duration' => 30,
        'verify_peer' => true,
        'verify_host' => true,
    ];

    /**
     * 创建默认的Symfony HttpClient实例
     */
    public static function createDefault(Config $config): HttpClientInterface
    {
        $options = $config->getHttp();
        unset($options['throw']);

        $options = array_replace(self::DEFAULT_OPTIONS, $options);
        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $options['headers'] = array_replace([
            'User-Agent' => sprintf(
                'EasyUmsPay/%s (PHP %s; %s)',
                UmsPay::VERSION,
                PHP_VERSION,
                PHP_OS,
            ),
        ], $headers);

        return HttpClient::create($options);
    }
}
