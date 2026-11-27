# AGENTS.md

本文件面向在本仓库内工作的代码代理（agent），用于快速建立正确上下文并减少对过时文档的误判。

## 1. 项目概览

- 仓库类型：单仓 PHP Composer Library
- 包名：`longquan/easy-umspay`
- 运行要求：PHP `^8.4`
- 主要依赖：`symfony/http-client ^8.1.12`、`monolog/monolog`、`psr/log`
- 测试与质量工具：Pest、PHPStan Level 8、Laravel Pint

当前 SDK 主要包含：

1. `Http`
   - 基于 `symfony/http-client` 的 API Client
   - `get/post/patch/put/delete`、`postJson/patchJson`、统一 `Response`
2. `SelfContract`
   - 商户自助签约/入网、图片上传、数据下载、资料上传、通用调用
   - 返回原始数组或字符串；业务错误由调用方检查 `res_code`
3. `Trade`
   - 新购物车（`shopping_cart`）与老购物车（`netpay`）两代协议
   - 单数组入参；`Payload::normalize()` 归一化数组出参
4. `Notify`
   - 两代购物车通知验签

统一入口是 `src/UmsPay.php`，由它持有配置、日志和底层 HTTP 客户端，并按需创建模块客户端。

## 2. 事实来源优先级

本仓库存在少量文档漂移。做判断时按以下优先级取信：

1. `composer.json`
2. `src/`
3. `tests/`
4. `docs/`、`README.md`

如果代码与文档冲突，优先修正文档，不要为了“匹配文档”去回退实现。

## 3. 仓库结构

### 代码目录

- `src/UmsPay.php`
  - SDK 主入口
- `src/Http`
  - `HttpClient`、`HttpClientFactory`、`Response`、`HttpLogging`、`Internal\LoggingResponse`
- `src/Supports`
  - `Config`、`Validator`
- `src/Logger`
  - 默认 Monolog 工厂
- `src/Exceptions`
  - 跨模块共享异常
- `src/Crypto`
  - 3DES、SHA-256、HMAC-SHA256
- `src/Auth`
  - 交易认证实现
- `src/SelfContract`
  - `Client`、`Enums`、`Exceptions`、`Supports`
  - 只保留 `Client` / `Enums` / `Exceptions` / `Supports`，不放请求/响应 DTO
- `src/Trade`
  - `TradeClient`、`Protocol`、`Support`、`Enums`、`Exceptions`
  - 只保留 `TradeClient` / `Protocol` / `Support` / `Enums` / `Exceptions`，不放请求/结果 DTO
- `src/Notify`
  - 通知验签策略与字段归一
- `src/Emuns/HttpMethod.php`
  - SelfContract 通用调用的 GET/POST 枚举
- `src/helpers.php`
  - 全局 `env()` helper，由 Composer `files` 自动加载

请求/响应直接使用数组与 Symfony `Response`，不引入额外的契约层。

### 测试目录

- `tests/Unit`
  - 单元测试主目录
- `tests/Feature`
  - `SelfContract`、`Trade`、`UmsPay` 特性测试
- `tests/Support`
  - 测试替身、logger 等复用类；namespace 为 `EasyUmsPay\Tests\Support`
- `tests/fixtures`
  - 图片等测试夹具

### 文档

- `README.md`
  - 总入口：安装、配置、快速开始、日志/错误摘要、开发命令
- `docs/api-client.md`
  - HTTP API Client、Response、options、异步、日志
- `docs/business-api.md`
  - SelfContract、Trade、Notify、Payload、错误处理

`docs/` 只保留上述两个使用文档。

## 4. 常用命令

以 `composer.json` 中实际存在的脚本为准：

```bash
composer install
composer test
composer test:unit
composer test:feature
composer test:coverage
composer phpstan
composer check-style
composer fix-style
composer check
composer fix
```

如果安装了 Git hooks，可运行：

```bash
./install-git-hooks.sh
```

该脚本会安装 `pre-commit`，提交前执行 `composer check`。

## 5. 代码约定

### 通用约定

- 所有 PHP 文件使用 `declare(strict_types=1);`
- 广泛使用强类型、`readonly`、枚举和值对象
- 命名空间与目录严格按 PSR-4：`EasyUmsPay\\` 对应 `src/`
- 公共 API 保持小而稳定，避免无必要抽象层

### 模块 API 约定

- `Trade` 请求参数使用**单个数组**；不要拆 `$order` + `$extra` 或新增 Request DTO。
- `Trade` 返回 `Payload::normalize()` 的固定键数组；不要再新增 `*Result` 类。
- `SelfContract` 返回原始数组或字符串；不要再新增 `*Response` 类。
- `HttpClient` 使用 Symfony options；业务模块只通过 `post()` / `get()` / `postJson()` 等 verb 方法调用。
- 不要引入额外的请求/响应契约层或模块 DTO。

### 校验约定

- `Trade` 不校验业务参数；缺字段、金额异常、未知字段都交给 UMS API 报错。
- `SelfContract` 不校验业务字段；`Image` 仍需做图片格式/大小校验，这是生成 data URI 的编码前提。
- 配置必填校验保留在 `Config`。
- 不要为了“更安全”重新加回请求字段白名单或提前业务校验。

### 日志约定

- HTTP 请求/响应日志默认关闭，由 `log.http.request` / `response` / `body` 控制。
- 请求日志可以记录完整 URL、headers、payload/options；响应日志必须惰性，在访问 `Response` 时记录 headers/body。
- 不实现析构日志；日志失败不能影响请求/响应。
- 日志可能包含 Authorization、Cookie 和业务 payload，文档需提示日志级别与脱敏。
- 业务模块不额外记录请求/响应日志，统一使用 HTTP 日志。

### 异常约定

- 共享异常保留在 `src/Exceptions`：`UmsException`、`ValidationException`、`RequestException`、`CryptoException`。
- 模块专属异常放在模块目录：
  - `SelfContract\Exceptions\SignatureException`
  - `Trade\Exceptions\UnsupportedSceneException`
- 不要新增语义重复的异常层；业务失败优先通过返回数组表达。

### 风格约定

- 代码风格由 Pint 管理，配置见 `pint.json`
- 默认使用单引号、按字母排序 import、数组多行尾逗号
- 不要手写与 Pint 冲突的格式

### 质量约定

- 静态分析目标为 PHPStan Level 8
- 新增/修改公开行为时，应同步补上 Pest 测试
- 测试尽量贴近现有风格：`beforeEach(...)`、`test('...', function () {})`
- 测试聚焦可观察行为、协议字段和错误分支

## 6. 模块级实现模式

### Http

关键事实：

- 客户端：`src/Http/HttpClient.php`
- 响应：`src/Http/Response.php`
- 工厂：`src/Http/HttpClientFactory.php`
- `HttpClient` 记录请求；响应由内部 `LoggingResponse` 惰性记录
- `request()` 返回 `Response` 前不访问底层响应
- `http.throw` 由 `Config::isHttpThrowEnabled()` 读取，工厂会从 Symfony options 中剔除

新增 verb 或 options 行为时：

1. 在 `HttpClient` 中新增公开方法或扩展 options 整理逻辑
2. 不要在 `request()` 中读取 response 做日志；响应日志由 `LoggingResponse` 在访问时记录
3. 为 `tests/Unit/Http` 补测试，覆盖请求选项和惰性行为

### SelfContract

关键事实：

- 客户端：`src/SelfContract/Client.php`
- 统一端点行为以 `src/SelfContract/Client.php` 和 `tests/Feature/SelfContract/UnifiedEndpointTest.php` 为准
- 请求经 `MessageBuilder` 组装明文，再做 SHA-256 签名与 3DES 加密
- 统一发送参数为：`json_data`、`sign_data`、`accesser_id`
- 业务错误返回数组，不抛业务异常
- 响应有 `sign` 但验签失败时抛 `SelfContract\Exceptions\SignatureException`
- `Image` 仍使用 `ValidationException` 做图片编码校验

新增接口时：

1. 在 `Client` 增加公开方法
2. 用 `buildRequestMessage()` 组装业务数据（必填字段 + 调用方 `$data`）
3. 走 `encryptAndSign()` 和 `sendPostRequest()` / `sendGetRequest()`
4. 直接返回 `parseResponse()` 的数组或字符串
5. 补充单元/特性测试

注意事项：

- 敏感字段通常在业务上传前由 SDK 加密，不要绕开现有加密链路
- 请求流水号由 `RequestSequenceGenerator` 负责
- 不要再创建请求/响应 DTO 类

### Trade

关键事实：

- 客户端：`src/Trade/TradeClient.php`
- 协议：`src/Trade/Protocol/NetPay`、`src/Trade/Protocol/ShoppingCart`
- 归一化：`src/Trade/Support/Payload.php`
- 请求参数是单个数组；`scene` 是独立参数
- 扩展字段在 Mapper 中通过 `array_replace($payload, $extra)` 合并到报文最后
- 不校验业务参数
- `Payload::normalize()` 返回固定键数组

新增能力时：

1. 确认落在 `NetPay` 还是 `ShoppingCart` 协议
2. 在对应 `Endpoints` / `Mapper` 中补端点与字段映射
3. 在 `ProtocolInterface` 和协议实现中补方法
4. 在 `TradeClient` 中补公开方法，统一走 `Payload::normalize()`
5. 为 Mapper 与 TradeClient 补测试

注意事项：

- `Payload::normalize()` 是 Trade 唯一结果形状；不要再新增 Result 类
- `close()` 的 `success` 要求 `TRADE_CLOSED`
- 两代成功码不同：老购物车 `errCode=SUCCESS`，新购物车 `respCode=0000`
- 扩展字段同名时覆盖 SDK 生成字段，文档需保持这一约定

### Notify

关键事实：

- 入口：`src/Notify/Verifier.php`
- 算法取通知报文 `signType` / `sign_type`，支持 SHA256、MD5
- `NotifyFields` 负责两代字段归一
- 缺失或未知算法一律返回 `false`

## 7. 测试与修改策略

建议遵循以下顺序：

1. 先确认改动属于哪个模块
2. 优先阅读对应测试与协议映射
3. 小步修改
4. 至少运行受影响测试
5. 若改动波及公共逻辑，再运行 `composer check`

推荐的最小验证集合：

- 仅改 `SelfContract`：`composer test -- tests/Unit/SelfContract tests/Feature/SelfContract`
- 仅改 `Trade`：`composer test -- tests/Unit/Trade tests/Feature/Trade`
- 改公共层（`Http`、`Config`、`Crypto`、`Supports`、`UmsPay`）：`composer check`

如果本地环境不完整，至少说明未执行哪些命令，不要假设检查已经通过。

## 8. 文档同步要求

任何以下变更都应同步检查文档：

- 新增/修改公开 API
- 修改配置项
- 修改请求/响应字段
- 修改异常命名空间
- 修改日志行为

文档分工：

- `README.md`：总入口和快速开始
- `docs/api-client.md`：HTTP Client / Response
- `docs/business-api.md`：SelfContract / Trade / Notify

不要新增失效链接；`docs/` 保持两份文档。

## 9. 提交前检查清单

- 变更是否符合现有目录与命名模式
- 是否保持 Trade 单数组、SelfContract 数组/字符串的返回约定
- 是否避免重新引入 Request/Result/Response DTO
- 是否补充或更新了相关 Pest 测试
- 是否运行了最小必要验证命令
- 是否修正文档中的失效类名、命令或链接
- 是否避免提交真实密钥、商户号或其他敏感信息

## 10. 给后续 Agent 的直接建议

- 先读代码，再读文档；不要把 `README` 当成唯一真相
- 改 `HttpClient` 前先看 `tests/Unit/Http/HttpClientTest.php`，特别是“返回 Response 前不访问响应”的测试
- 改 `Trade` 前先对照 `TradeClient`、`Protocol`、`Mapper`、`Payload` 四层
- 改 `SelfContract` 前先看 `src/SelfContract/Client.php` 与 `tests/Feature/SelfContract/UnifiedEndpointTest.php`
- 发现文档漂移时顺手修，不要把问题留给下一个人
