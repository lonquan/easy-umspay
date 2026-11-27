# HTTP API Client

`EasyUmsPay\Http\HttpClient` 是基于 `symfony/http-client` 的薄封装，负责请求参数整理、发送和响应包装。它**不负责 UMS 的签名、加密和业务解析**；业务接口优先使用 `trade()` / `selfContract()`。

## 获取客户端

```php
$api = $umsPay->getApiClient();
```

`getApiClient()` 返回的客户端复用当前配置的超时、代理、证书、`http.throw` 和 logger。

## 请求方法

```php
$response = $api->get($uri, $options);
$response = $api->post($uri, $options);
$response = $api->patch($uri, $options);
$response = $api->put($uri, $options);
$response = $api->delete($uri, $options);

$response = $api->postJson($url, $data, $options);
$response = $api->patchJson($url, $data, $options);
$response = $api->request($method, $url, $options);
```

表单与批量请求：

```php
$response = $api->postForm($uri, $data);
$results = $api->batchPostForm($uri, $payloads);
```

## options 规则

`$options` 直接接受 Symfony `HttpClientInterface` 的选项，例如 `query`、`headers`、`body`、`timeout`、`max_duration`、`base_uri`、`verify_peer`。

默认 client 没有 `base_uri`；使用相对路径时需要在 `http` 配置或构造时提供 `base_uri`，否则请传绝对 URL。

EasyWeChat 风格简写：

```php
// GET 简写参数进入 query
$api->get('https://example.com/foo', ['page' => 1]);

// POST 简写参数进入 form body
$api->post('https://example.com/foo', ['name' => 'bar']);
```

规则：

- `GET` / `HEAD` / `DELETE` 未识别键进入 `query`。
- `POST` / `PUT` / `PATCH` 未识别键进入 `body`。
- 已传 `json`，或 `Content-Type` 为 JSON 时，未识别键进入 `json`。
- `body` 为数组时由 Symfony 编码为 `application/x-www-form-urlencoded`。
- `json` 由 SDK 使用 `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` 编码成 `body`。
- 同时传 `json` 和非空 `body` 会抛 `RequestException`。
- `postJson()` / `patchJson()` 的第三个参数是 options，不是 headers；附加头请写 `['headers' => [...]]`。

示例：

```php
$response = $api->postJson(
    'https://example.com/open/api',
    ['orderNo' => 'ORDER1'],
    [
        'headers' => [
            'Authorization' => 'Bearer token',
        ],
        'timeout' => 10,
    ],
);
```

## 响应

所有请求返回 `EasyUmsPay\Http\Response`，它实现 Symfony `ResponseInterface` 和 `ArrayAccess`。

| 方法 | 说明 |
| --- | --- |
| `getStatusCode()` | HTTP 状态码 |
| `getHeaders()` / `getHeader()` / `getHeaderLine()` / `hasHeader()` | 响应头 |
| `getContent()` / `toArray()` / `toJson()` | 响应内容 |
| `isSuccessful()` / `isFailed()` | HTTP 成功判断，可配合 failure judge |
| `throw()` / `throwOnFailure()` / `quietly()` | 切换访问内容时的异常行为 |
| `judgeFailureUsing(callable)` | 自定义业务失败判断 |
| `saveAs($path)` / `toDataUrl()` | 保存文件 / Data URL |
| `getInfo()` / `cancel()` | 透传底层响应 |

示例：

```php
$response = $api->get('https://example.com/open/api', ['page' => 1]);

$status = $response->getStatusCode();
$data = $response->toArray();

if ($response->isFailed()) {
    // HTTP 失败
}
```

业务错误判断：

```php
$response->judgeFailureUsing(
    static fn (EasyUmsPay\Http\Response $item): bool => ($item['res_code'] ?? '') !== '0000',
);
```

## http.throw

```php
$umsPay = new UmsPay([
    'http' => [
        'throw' => false,
    ],
]);
```

- `true`（默认）：访问 `getContent()` / `toArray()` 等遇到 4xx/5xx 时抛 Symfony HTTP 异常。
- `false`：不抛 HTTP 异常，由调用方通过 `isFailed()`、`getContent(false)`、`quietly()` 自行处理。
- `http.throw` 是 SDK 专有键，不会传给 Symfony HttpClient。

## 异步与惰性

请求是惰性的：`get()` / `postJson()` 返回 `Response` 时不会读取 status、headers 或 body；调用方第一次访问响应信息时才真正等待响应。

```php
$first = $api->get('https://example.com/first');
$second = $api->get('https://example.com/second');

// 访问任意一个响应时才执行/等待请求
$firstData = $first->toArray();
$secondData = $second->toArray();
```

## 日志

HTTP 请求/响应日志默认关闭，按需开启：

```php
'log' => [
    'enabled' => true,
    'default' => 'stderr',
    'channels' => [
        'stderr' => [
            'driver' => 'stderr',
            'level' => 'debug',
        ],
    ],
    'http' => [
        'request' => true,
        'response' => true,
        'body' => true,
    ],
],
```

- `request=true`：记录 method、URL、query、headers、payload 和关键 options。
- `response=true`：记录 status、headers、content-type 和 body。
- `body=false`：只记录 URL/headers/status，不记录 payload/body。
- 未配置 `log` 或 `log.enabled=false`：SDK 不自动创建 logger；显式传入的 logger 仍会使用。
- `log.default` 缺失或对应通道不存在时，降级到内置 `stderr`（`info`）；配置了 `channels.stderr` 时优先使用该通道。
- 响应日志是惰性的：返回 `Response` 时不读取，调用方访问响应时才记录，不访问不会提前发请求。
- HTTP 日志使用 `debug` 级别，示例通过 `level=debug` 的通道输出；`config/umspay.php` 默认 `daily` + `debug`，写入 `logs/umspay.log`。
- logger 仍会传给实现了 `LoggerAwareInterface` 的底层 Symfony client，传输层可能额外记录 `Request: "..."` 元信息。
- 日志可能包含 Authorization、Cookie 和业务 payload，生产环境注意日志级别与脱敏。
- `batchPostForm()` 记录批量摘要（url/count/keys），每个响应在被访问时惰性记录 headers/body。

## 限制

- 不实现 XML 请求。
- 不内置重试策略。
- 不提供 PSR-7 转换。
- 原始 Client 不负责 UMS 签名；UMS 业务接口请使用 `trade()` 和 `selfContract()`。
