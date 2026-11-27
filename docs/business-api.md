# 业务 API

本文档覆盖 `SelfContract`、`Trade`、`Notify` 三个业务模块。HTTP 底层用法见 [api-client.md](./api-client.md)。

## 配置

```php
use EasyUmsPay\UmsPay;

$umsPay = new UmsPay([
    'log' => [
        'enabled' => true,
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
            'request' => false,
            'response' => false,
            'body' => false,
        ],
    ],
    'http' => [
        'timeout' => 15,
        'max_duration' => 30,
        'throw' => true,
    ],
    'self_contract' => [
        'base_url' => 'https://selfapply-test.chinaums.com',
        'accesser_id' => 'your_accesser_id',
        'sign_key' => '123456789012345678901234',
    ],
    'trade' => [
        'protocol' => 'shopping_cart', // shopping_cart | netpay
        'base_url' => 'https://test-api-open.chinaums.com',
        'app_id' => 'your_app_id',
        'app_key' => 'your_app_key',
        'merchant_no' => 'your_merchant_no',
        'terminal_no' => 'your_terminal_no',
        'message_source' => 'OPTIONAL',
        'inst_mid' => 'OPTIONAL',
    ],
]);
```

- `self_contract.base_url` / `accesser_id` / `sign_key` 必填。
- `trade.protocol` / `base_url` / `app_id` / `app_key` / `merchant_no` / `terminal_no` 必填。
- `trade.message_source`、`trade.inst_mid` 可选。
- `http.throw` 控制访问响应时是否抛 HTTP 异常；详见 [api-client.md](./api-client.md)。

## SelfContract

```php
$selfContract = $umsPay->selfContract();
```

### 上传图片

```php
$response = $selfContract->uploadImage('license.jpg');

if (($response['res_code'] ?? '') !== '0000') {
    // 业务失败，读取 $response['res_msg']
}

$filePath = $response['file_path'] ?? '';
```

支持本地路径、URL、base64、data URI 和 `EasyUmsPay\SelfContract\Supports\Image`：

```php
use EasyUmsPay\SelfContract\Supports\Image;

$response = $selfContract->uploadImage(Image::make('license.jpg'));
```

附加请求参数：

```php
$response = $selfContract->uploadImage('license.jpg', [
    'custom_field' => 'value',
]);
```

`$data` 与必填字段合并，同名键覆盖；会进入加密签名明文报文。

### 批量上传

```php
$results = $selfContract->uploadImages([
    'license' => 'license.jpg',
    'id_front' => Image::make('id-card-front.jpg'),
], [
    'store_id' => 'S001',
]);

foreach ($results as $key => $item) {
    if (! $item['success']) {
        // 传输 / JSON / 验签失败
        $error = $item['error'];
        continue;
    }

    // 请求和解析成功；业务结果仍看 res_code
    $response = $item['response'];
}
```

- 结果保留原键名。
- `success` 表示 HTTP/JSON/验签流程成功，**不代表** `res_code` 成功。
- `$data` 对每张图片生效。
- 空数组直接返回 `[]`。

### 数据下载

```php
use EasyUmsPay\SelfContract\Enums\DataType;

$content = $selfContract->downloadData(DataType::Province);

// 成功时通常是 CSV 字符串；上游返回 JSON 错误时也原样返回字符串。
```

附加参数：

```php
$content = $selfContract->downloadData(DataType::Province, [
    'custom_field' => 'value',
]);
```

### 支行查询 / 资料上传 / 通用调用

```php
$banks = $selfContract->queryBanks('340000', '合肥');
$result = $selfContract->applyContract([
    'ums_reg_id' => 'REG123456',
    // ...
]);

$response = $selfContract->call('nmrs_store_change', [
    'ums_reg_id' => 'REG123456',
    'store_name' => '示例分店',
]);
```

### 自定义请求流水号

默认使用 `RequestSequenceGenerator` 生成 14 位时间 + 6 位微秒 + 10 位随机十六进制的 30 位流水号。如需接入自有序列，可设置进程级生成器：

```php
use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;

RequestSequenceGenerator::setGenerator(static function (): string {
    return '...';
});
```

- 设置后对所有 SelfContract 请求生效；批量上传会为每个子请求各调用一次。
- 传 `null` 恢复默认：`RequestSequenceGenerator::setGenerator(null);`。
- 该配置是进程级静态状态，长驻进程/多租户场景需注意全局影响。
- SDK 不校验返回值格式和长度，需实现方保证符合 UMS 的 `request_seq` 要求。

### 业务错误与验签

- SelfContract **不抛业务异常**；`res_code` / `res_msg` 在返回数组中，由调用方判断。
- JSON 无法解析时抛 `EasyUmsPay\Exceptions\RequestException`。
- 响应含 `sign` 且验签失败时抛 `EasyUmsPay\SelfContract\Exceptions\SignatureException`。
- `Image` 仍会校验图片格式/大小，非法图片抛 `EasyUmsPay\Exceptions\ValidationException`（这是编码前提，不是 API 参数校验）。

## Trade

```php
use EasyUmsPay\Trade\Enums\PayScene;

$trade = $umsPay->trade();
```

### 请求参数

所有请求参数放在**同一个数组**中，不拆 `$order` / `$extra`。

`pay()` 常用字段：

| 字段 | 说明 |
| --- | --- |
| `orderNo` | 商户订单号 |
| `amount` | 金额（分） |
| `description` | 商品描述 |
| `notifyUrl` | 异步通知地址 |
| `payerId` | 微信 openid / 支付宝 userId |
| `subAppId` | 小程序/公众号 appid |
| `expireAt` | 过期时间，`DateTimeInterface` |
| `returnUrl` | H5 返回地址 |
| `attachedData` | 附加数据 |
| `instMid` | 业务类型覆盖 |

`refund()` 常用字段：

- `orderNo`、`refundOrderNo`、`amount`

其他任意键会原样合并到请求报文；同名键会覆盖 SDK 生成的字段。`scene` 是独立参数，不作为报文参数传递。

SDK **不校验业务参数**；缺字段或类型不符合预期时仍会拼报文，由 UMS API 返回错误。

### 下单

```php
$result = $trade->pay([
    'orderNo' => 'ORDER' . time(),
    'amount' => 100,
    'description' => '测试商品',
    'notifyUrl' => 'https://example.com/notify',
    'payerId' => 'openid-1',
], PayScene::WeChatMiniApp);

if (! $result['success']) {
    // $result['errorCode'] / $result['errorMessage']
}

$payParams = $result['payParams'];
```

### 查询 / 关单

```php
$query = $trade->query('ORDER123456', PayScene::WeChatMiniApp);

// 也支持数组形式，附加任意字段
$query = $trade->query([
    'orderNo' => 'ORDER123456',
    'custom_field' => 'value',
], PayScene::WeChatMiniApp);

$close = $trade->close('ORDER123456', PayScene::WeChatMiniApp);

if (! $close['success']) {
    // 关单未确认，errorCode 可能为 CLOSE_UNCONFIRMED
}
```

### 退款 / 退款查询

```php
$refund = $trade->refund([
    'orderNo' => 'ORDER123456',
    'refundOrderNo' => 'REFUND123456',
    'amount' => 30,
], PayScene::WeChatMiniApp);

$refundQuery = $trade->refundQuery('REFUND123456', PayScene::WeChatMiniApp);
```

### 返回数组

所有 Trade 方法返回固定键数组：

| 键 | 类型 | 说明 |
| --- | --- | --- |
| `success` | bool | 是否成功；close 要求 `TRADE_CLOSED` |
| `orderNo` | ?string | 渠道回传订单号，缺失时回退请求值 |
| `seqId` | ?string | 平台/渠道流水 |
| `targetOrderId` | ?string | 渠道交易号 |
| `status` | ?TradeStatus | 交易状态 |
| `amount` | ?int | 金额（分） |
| `paidAt` | ?DateTimeImmutable | 支付时间 |
| `refundOrderNo` | ?string | 退款单号，缺失时回退请求值 |
| `refundTargetOrderId` | ?string | 渠道退款流水 |
| `refundStatus` | ?RefundStatus | 退款状态 |
| `payParams` | array | 调起支付参数；仅 pay 填充 |
| `errorCode` | string | 成功时为空 |
| `errorMessage` | string | 成功时为空 |
| `raw` | array | 渠道原始报文 |

需要自行归一原始报文时，可使用静态方法：

```php
use EasyUmsPay\Trade\Support\Payload;

$result = Payload::normalize($raw, PayScene::WeChatMiniApp);
```

### 两代协议差异

| 语义 | 老购物车 `netpay` | 新购物车 `shopping_cart` |
| --- | --- | --- |
| 成功码 | `errCode === 'SUCCESS'` | `respCode === '0000'` |
| 错误描述 | `errMsg` | `respDesc` |
| 订单号 | `merOrderId` | `mchntOrderId` |
| 平台流水 | `seqId` / `pltSsn` | `seqId` / `pltSsn` |

`Payload::normalize()` 已统一这些差异；业务调用方通常不需要直接判断原始字段。

## Notify

```php
use EasyUmsPay\Notify\Verifier;
use EasyUmsPay\Trade\Enums\TradeProtocol;

$fields = $_POST; // 表单原文，已 URL 解码

$ok = Verifier::forProtocol(TradeProtocol::ShoppingCart)
    ->verify($fields, 'your_notify_key');

if (! $ok) {
    http_response_code(400);
    exit('invalid signature');
}
```

`Verifier` 从报文 `signType` / `sign_type` 选择 SHA256 或 MD5；缺失或未知算法返回 `false`。

## 异常

共享异常位于 `EasyUmsPay\Exceptions`：

- `UmsException`：基类
- `ValidationException`：配置、图片编码
- `RequestException`：HTTP/传输/JSON 解析
- `CryptoException`：加密

模块异常：

- `EasyUmsPay\SelfContract\Exceptions\SignatureException`
- `EasyUmsPay\Trade\Exceptions\UnsupportedSceneException`
