# EasyUmsPay - 银联商务 UMS 支付 SDK

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

面向银联商务 UMS 接口的 PHP SDK：统一封装 HTTP 调用、商户自助签约与两代购物车支付协议。

## 功能

| 模块 | 能力 |
| --- | --- |
| `Http` | 基于 `symfony/http-client` 的轻量客户端：`get` / `post` / `put` / `patch` / `delete`、`postJson` / `patchJson`、`postForm`、并发表单 `batchPostForm`；兼容 Symfony HttpClient options 与 EasyWeChat 风格简写；`Response` 提供 `toArray` / `toJson` / `saveAs` / `toDataUrl` / `quietly` / `throw`；返回 `Response` 时不读取状态、响应头和正文，访问时才等待请求 |
| `SelfContract` | 商户入网：`uploadImage` / `uploadImages` 图片上传、`downloadData` 数据下载、`queryBanks` 支行查询、`applyContract` 资料上传、`call` 通用调用；报文经 3DES(ECB) 加密并做 SHA-256 签名；`RequestSequenceGenerator::setGenerator()` 可自定义请求流水号（默认 30 位） |
| `Trade` | 新购物车 `shopping_cart` 与老购物车 `netpay` 共用 `pay` / `query` / `close` / `refund` / `refundQuery`；单数组入参、不做业务参数校验、支持任意扩展字段透传；`Payload::normalize()` 统一两代字段与成功码，返回固定键数组；`close()` 的成功要求渠道返回 `TRADE_CLOSED` |
| `Notify` | 两代购物车通知验签；算法取报文 `signType` / `sign_type`，支持 SHA256 与 MD5，缺失或未知一律返回 `false` |

## 环境要求

- PHP `^8.4`（Symfony HttpClient 8.x 要求）、Composer `>= 2.0`
- 扩展：`ext-openssl`、`ext-fileinfo`

## 安装

```bash
composer require longquan/easy-umspay
```

可直接复用仓库里的 `config/umspay.php`（Laravel 风格，支持 `env()`）。

## 快速开始

```php
use EasyUmsPay\UmsPay;

$umsPay = new UmsPay([
    'log' => [
        'enabled' => true,          // 必须显式开启，SDK 不会自动创建 logger
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => 'logs/umspay.log',
                'level' => 'debug',
                'days' => 7,
            ],
        ],
        'http' => [
            'request' => false,     // HTTP 请求/响应日志默认关闭
            'response' => false,
            'body' => false,
        ],
    ],
    'http' => [
        'timeout' => 15,
        'max_duration' => 30,
        'throw' => true,            // false 时访问响应不抛 HTTP 异常
    ],
    'self_contract' => [
        'base_url' => 'https://selfapply-test.chinaums.com',
        'accesser_id' => 'your_accesser_id',
        'sign_key' => '123456789012345678901234', // 24 字节
    ],
    'trade' => [
        'protocol' => 'shopping_cart',            // shopping_cart | netpay
        'base_url' => 'https://test-api-open.chinaums.com',
        'app_id' => 'your_app_id',
        'app_key' => 'your_app_key',
        'merchant_no' => 'your_merchant_no',
        'terminal_no' => 'your_terminal_no',
    ],
]);
```

## 配置说明

| 配置 | 说明 |
| --- | --- |
| `log` | PSR-3 日志配置：`enabled`、`default`、`channels`（`single` / `daily` / `stderr` / `stack`）、`http`；未配置或 `enabled` 不为 `true` 时视为关闭 |
| `http` | Symfony HttpClient 选项；`throw` 是 SDK 专有键，控制访问响应时是否抛 HTTP 异常 |
| `self_contract` | `base_url`、`accesser_id`、`sign_key`（24 字节，用于 3DES 与 SHA-256） |
| `trade` | `protocol`、`base_url`、`app_id`、`app_key`、`merchant_no`、`terminal_no`；可选 `message_source`、`inst_mid` |

## 用法

### 下单

```php
use EasyUmsPay\Trade\Enums\PayScene;

$result = $umsPay->trade()->pay([
    'orderNo' => 'ORDER' . time(),
    'amount' => 100,                 // 单位：分
    'description' => '测试商品',
    'notifyUrl' => 'https://example.com/notify',
    'payerId' => 'openid-1',
], PayScene::WeChatMiniApp);

if (! $result['success']) {
    echo $result['errorCode'] . ': ' . $result['errorMessage'];
}

$payParams = $result['payParams'];  // 交给前端调起支付
```

查询、关单、退款、退款查询返回同一套固定键数组；`query()`、`close()`、`refundQuery()` 支持字符串或数组入参：

```php
$query = $umsPay->trade()->query('ORDER123456', PayScene::WeChatMiniApp);
$close = $umsPay->trade()->close('ORDER123456', PayScene::WeChatMiniApp);

$refund = $umsPay->trade()->refund([
    'orderNo' => 'ORDER123456',
    'refundOrderNo' => 'REFUND123456',
    'amount' => 30,
], PayScene::WeChatMiniApp);
```

字段明细见 [业务 API](docs/business-api.md#trade)。

### 商户入网

```php
use EasyUmsPay\SelfContract\Enums\DataType;

$response = $umsPay->selfContract()->uploadImage('license.jpg');  // 数组，含 file_path
$batch = $umsPay->selfContract()->uploadImages([
    'license' => 'license.jpg',
    'id_front' => 'id-card-front.jpg',
]);

$csv = $umsPay->selfContract()->downloadData(DataType::Province); // 字符串
```

业务错误通过返回数组的 `res_code` / `res_msg` 判断，响应验签失败抛 `EasyUmsPay\SelfContract\Exceptions\SignatureException`。完整方法见 [业务 API](docs/business-api.md#selfcontract)。

### 通知验签

```php
use EasyUmsPay\Notify\Verifier;
use EasyUmsPay\Trade\Enums\TradeProtocol;

$ok = Verifier::forProtocol(TradeProtocol::ShoppingCart)
    ->verify($_POST, 'your_notify_key');
```

### 通用 HTTP 调用

```php
$response = $umsPay->getApiClient()->postJson(
    'https://example.com/open/api',
    ['orderNo' => 'ORDER1'],
    ['headers' => ['Authorization' => 'Bearer token']],
);

$data = $response->toArray();
```

原始 Client 不负责 UMS 签名/加密，业务接口优先用 `trade()` / `selfContract()`。完整用法见 [HTTP API Client](docs/api-client.md)。

## 日志

- 未配置 `log` 时不自动创建 logger；需要日志请显式设置 `log.enabled=true`。
- `log.default` 缺失或通道不存在时降级到内置 `stderr`（`info`）；已配置 `channels.stderr` 时优先使用该通道。
- HTTP 请求/响应日志默认关闭，由 `log.http.request` / `response` / `body` 控制，统一 `debug` 级别。
- 开启后记录 method、URL、query、headers、payload、status 与正文；`body=false` 时只记录 URL/headers/status。
- 响应日志是惰性的：返回 `Response` 时不读取，访问时才记录。
- 日志可能包含 Authorization、Cookie 和业务 payload，生产环境注意级别与脱敏。

细节与完整示例见 [HTTP API Client / 日志](docs/api-client.md#日志)。

## 错误处理

| 场景 | 处理方式 |
| --- | --- |
| Trade 业务失败 | 不抛业务异常，检查返回数组的 `success` / `errorCode` / `errorMessage` |
| SelfContract 业务失败 | 返回数组，检查 `res_code` / `res_msg` |
| SelfContract 响应验签失败 | 抛 `EasyUmsPay\SelfContract\Exceptions\SignatureException` |
| HTTP 或 JSON 解析错误 | 抛 `EasyUmsPay\Exceptions\RequestException` |
| 配置、图片编码校验失败 | 抛 `EasyUmsPay\Exceptions\ValidationException` |
| 新购物车不支持的场景 | 抛 `EasyUmsPay\Trade\Exceptions\UnsupportedSceneException` |

## 开发

```bash
composer install
composer check    # Laravel Pint + PHPStan Level 8 + Pest
```

单项命令：`composer test`、`composer phpstan`、`composer check-style`、`composer fix-style`。

## License

MIT
