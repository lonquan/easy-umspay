<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    |
    | 配置日志的启用状态、默认通道以及各个日志通道的详细设置
    |
    */
    'log' => [
        /*
        |----------------------------------------------------------------------
        | 启用日志
        |----------------------------------------------------------------------
        |
        | 是否启用日志记录功能
        |
        */
        'enabled' => env('UMS_LOG_ENABLED', true),

        /*
        |----------------------------------------------------------------------
        | 默认日志通道
        |----------------------------------------------------------------------
        |
        | 指定默认使用的日志通道，可选值见下方 channels 配置
        |
        */
        'default' => env('UMS_LOG_CHANNEL', 'daily'),

        /*
        |----------------------------------------------------------------------
        | 日志通道配置
        |----------------------------------------------------------------------
        |
        | 支持多种日志输出方式，可根据需要选择不同的通道
        |
        */
        'channels' => [
            // 单文件日志
            'single' => [
                'driver' => 'single',
                'path' => env('UMS_LOG_PATH', getcwd() . '/logs/umspay.log'),
                'level' => env('UMS_LOG_LEVEL', 'debug'),
                'replace_placeholders' => true,
            ],

            // 按日期分割的日志文件
            'daily' => [
                'driver' => 'daily',
                'path' => env('UMS_LOG_PATH', getcwd() . '/logs/umspay.log'),
                'level' => env('UMS_LOG_LEVEL', 'debug'),
                'days' => (int) env('UMS_LOG_DAYS', 7),
                'replace_placeholders' => true,
            ],

            // 标准错误输出（控制台输出）
            'stderr' => [
                'driver' => 'stderr',
                'level' => env('UMS_LOG_LEVEL', 'debug'),
                'replace_placeholders' => true,
            ],

            // 多通道组合（同时输出到多个通道）
            'stack' => [
                'driver' => 'stack',
                'channels' => ['daily', 'stderr'],
                'ignore_exceptions' => false,
            ],
        ],

        // HTTP 请求/响应日志开关，默认全部关闭
        'http' => [
            'request' => (bool) env('UMS_LOG_HTTP_REQUEST', false),
            'response' => (bool) env('UMS_LOG_HTTP_RESPONSE', false),
            'body' => (bool) env('UMS_LOG_HTTP_BODY', false),
        ],
    ],

    // Symfony HttpClient 选项，会与 SDK 默认值合并。
    // throw 是 SDK 专有键，不会传给 Symfony；false 时访问响应不抛 HTTP 异常。
    'http' => [
        'timeout' => (float) env('UMS_HTTP_TIMEOUT', 10),
        'max_duration' => (float) env('UMS_HTTP_MAX_DURATION', 30),
        'verify_peer' => (bool) env('UMS_HTTP_VERIFY_PEER', true),
        'verify_host' => (bool) env('UMS_HTTP_VERIFY_HOST', true),
        'throw' => (bool) env('UMS_HTTP_THROW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 自助签约配置
    |--------------------------------------------------------------------------
    |
    | 商户入网、信息变更等功能使用
    |
    | 加密说明：
    | - 加密算法：DESede/ECB/PKCS5Padding (TripleDES ECB 模式)
    | - ECB 模式不需要初始化向量（IV）
    | - 密钥长度：24 字节
    |
    */
    'self_contract' => [
        // API基础地址（必填，请根据实际接入环境填写）
        // 测试环境: https://selfapply-test.chinaums.com
        // 生产环境: https://yinshangpai.chinaums.com
        'base_url' => env('UMS_SELF_CONTRACT_BASE_URL', ''),

        // 接入方ID
        'accesser_id' => env('UMS_SELF_CONTRACT_ACCESSER_ID', ''),

        // 签名密钥（24字节）- 用于 3DES 加密敏感字段和 SHA256 签名
        'sign_key' => env('UMS_SELF_CONTRACT_SIGN_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 交易配置
    |--------------------------------------------------------------------------
    |
    | 支付、查询、退款等功能使用；支持新购物车与老购物车两代协议。
    |
    */
    'trade' => [
        // 协议：shopping_cart（新购物车）| netpay（老购物车）
        'protocol' => env('UMS_TRADE_PROTOCOL', 'shopping_cart'),

        // API基础地址（必填，请根据实际接入环境填写）
        // 新购物车测试环境: https://test-api-open.chinaums.com
        // 新购物车生产环境: https://api-mop.chinaums.com
        'base_url' => env('UMS_TRADE_BASE_URL', ''),

        // 应用ID
        'app_id' => env('UMS_TRADE_APP_ID', ''),

        // 应用密钥
        'app_key' => env('UMS_TRADE_APP_KEY', ''),

        // 商户号
        'merchant_no' => env('UMS_TRADE_MERCHANT_NO', ''),

        // 终端号
        'terminal_no' => env('UMS_TRADE_TERMINAL_NO', ''),

        // 报文中 msgSrc 的来源标识，可选
        'message_source' => env('UMS_TRADE_MESSAGE_SOURCE', ''),

        // 业务类型覆盖，可选；缺省按场景推导
        'inst_mid' => env('UMS_TRADE_INST_MID', ''),
    ],
];
