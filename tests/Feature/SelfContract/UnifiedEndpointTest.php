<?php

declare(strict_types=1);

use EasyUmsPay\Crypto\TripleDESCrypto;
use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\SelfContract\Client;
use EasyUmsPay\SelfContract\Enums\DataType;
use EasyUmsPay\SelfContract\Supports\Image;
use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;
use EasyUmsPay\Supports\Config;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

afterEach(function () {
    RequestSequenceGenerator::setGenerator(null);
});

const UNIFIED_ENDPOINT = '/self-contract-nmrs/interface/autoReg';

function createUnifiedClient(MockHttpClient $mockHttpClient): Client
{
    $config = new Config([
        'self_contract' => [
            'accesser_id' => 'TEST_ID',
            'sign_key' => '123456789012345678901234',
            'base_url' => 'https://selfapply-test.chinaums.com',
        ],
    ]);

    $httpClient = new HttpClient($mockHttpClient, new NullLogger);

    return new Client($config, $httpClient);
}

test('上传图片使用统一端点', function () {
    $capturedUrl = null;
    $mockHttpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
        $capturedUrl = $url;

        return new MockResponse('{"res_code":"0000","res_msg":"上传成功"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    expect($capturedUrl)->toEndWith(UNIFIED_ENDPOINT);
});

test('上传图片支持字符串输入并使用统一端点', function () {
    $capturedUrl = null;
    $mockHttpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
        $capturedUrl = $url;

        return new MockResponse('{"res_code":"0000","res_msg":"上传成功"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(selfContractSamplePngBase64());

    expect($capturedUrl)->toEndWith(UNIFIED_ENDPOINT);
});

test('下载数据使用GET方法和统一端点', function () {
    $capturedUrl = null;
    $capturedMethod = null;
    $mockHttpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl, &$capturedMethod) {
        $capturedUrl = $url;
        $capturedMethod = $method;

        return new MockResponse('{"res_code":"0000","res_msg":"成功"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->downloadData(DataType::Province);

    expect($capturedUrl)->toContain(UNIFIED_ENDPOINT)
        ->and($capturedMethod)->toBe('GET');
});

test('所有请求都有三个必需参数', function () {
    $capturedOptions = null;

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return new MockResponse('{"res_code":"0000","res_msg":"成功"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    // POST请求使用 application/x-www-form-urlencoded 格式
    // 验证三参数
    expect($capturedOptions)->toHaveKey('body');
    parse_str($capturedOptions['body'], $bodyData);
    expect($bodyData)->toHaveKeys(['json_data', 'sign_data', 'accesser_id']);
});

test('json_data是Base64编码的', function () {
    $capturedOptions = null;

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return new MockResponse('{"res_code":"0000"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    // json_data应该是Base64
    parse_str($capturedOptions['body'], $bodyData);
    expect($bodyData)->toHaveKey('json_data');
    expect($bodyData['json_data'])->toBeString();
    $jsonData = is_string($bodyData['json_data']) ? $bodyData['json_data'] : '';
    $decoded = base64_decode($jsonData, true);
    expect($decoded)->not->toBeFalse('json_data is not valid Base64');
});

test('加密前报文中的 pic_base64 是完整 data URI', function () {
    $capturedOptions = null;

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return new MockResponse('{"res_code":"0000"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    parse_str($capturedOptions['body'], $bodyData);
    $encryptedJson = is_string($bodyData['json_data'] ?? null) ? $bodyData['json_data'] : '';
    $crypto = new TripleDESCrypto('123456789012345678901234');
    $plaintext = $crypto->decrypt($encryptedJson);
    $message = json_decode($plaintext, true);

    expect($message)->toBeArray()
        ->and($message['pic_base64'] ?? null)->toBe(selfContractSamplePngDataUri());
});

test('sign_data是64字符十六进制', function () {
    $capturedOptions = null;

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return new MockResponse('{"res_code":"0000"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    // sign_data应该是64字符十六进制
    parse_str($capturedOptions['body'], $bodyData);
    expect($bodyData)->toHaveKey('sign_data');
    $signData = is_string($bodyData['sign_data']) ? $bodyData['sign_data'] : '';
    expect($signData)->toBeString()
        ->and(strlen($signData))->toBe(64)
        ->and($signData)->toMatch('/^[a-fA-F0-9]{64}$/');
});

test('业务错误返回数组', function () {
    $mockHttpClient = new MockHttpClient(
        new MockResponse('{"res_code":"9001","res_msg":"参数错误"}')
    );

    $client = createUnifiedClient($mockHttpClient);
    $response = $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    expect($response['res_code'])->toBe('9001')
        ->and($response['res_msg'])->toBe('参数错误');
});

test('批量上传图片仍使用统一端点和三参数', function () {
    $capturedUrls = [];
    $capturedBodies = [];

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedUrls, &$capturedBodies) {
        $capturedUrls[] = $url;
        $capturedBodies[] = $options['body'] ?? '';

        return new MockResponse('{"res_code":"0000","res_msg":"成功"}');
    });

    $client = createUnifiedClient($mockHttpClient);
    $results = $client->uploadImages([
        'license' => selfContractSamplePngBase64(),
        'id_card' => Image::make(selfContractSamplePngBase64()),
    ]);

    expect($capturedUrls)->toHaveCount(2)
        ->and($capturedUrls[0])->toEndWith(UNIFIED_ENDPOINT)
        ->and($capturedUrls[1])->toEndWith(UNIFIED_ENDPOINT)
        ->and($results['license']['success'])->toBeTrue()
        ->and($results['id_card']['success'])->toBeTrue();

    foreach ($capturedBodies as $body) {
        parse_str((string) $body, $bodyData);
        expect($bodyData)->toHaveKeys(['json_data', 'sign_data', 'accesser_id']);
    }
});

test('批量上传图片预处理失败时不会发送请求', function () {
    $requests = 0;
    $mockHttpClient = new MockHttpClient(function () use (&$requests) {
        $requests++;

        return new MockResponse('{"res_code":"0000"}');
    });

    $client = createUnifiedClient($mockHttpClient);

    expect(fn () => $client->uploadImages([
        'license' => selfContractSamplePngBase64(),
        'invalid' => '',
    ]))->toThrow(ValidationException::class, '图片来源不能为空');

    expect($requests)->toBe(0);
});

test('批量上传图片可返回部分业务失败结果', function () {
    $mockHttpClient = new MockHttpClient([
        new MockResponse('{"res_code":"0000","res_msg":"成功","file_path":"PIC1"}'),
        new MockResponse('{"res_code":"9001","res_msg":"参数错误"}'),
    ]);

    $client = createUnifiedClient($mockHttpClient);
    $results = $client->uploadImages([
        'license' => selfContractSamplePngBase64(),
        'id_card' => selfContractSamplePngDataUri(),
    ]);

    $licenseResult = $results['license'];
    $idCardResult = $results['id_card'];

    expect($licenseResult['success'])->toBeTrue();
    if (! isset($licenseResult['response'])) {
        throw new RuntimeException('Expected successful batch upload response for license.');
    }
    $licenseResponse = $licenseResult['response'];
    expect($licenseResponse)->toBeArray()
        ->and($licenseResponse['file_path'])->toBe('PIC1');
    expect($idCardResult['success'])->toBeTrue();
    if (! isset($idCardResult['response'])) {
        throw new RuntimeException('Expected parsed batch response for id_card.');
    }
    expect($idCardResult['response']['res_code'])->toBe('9001');
});

test('自定义流水号生成器用于请求报文', function () {
    $capturedOptions = null;

    $mockHttpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return new MockResponse('{"res_code":"0000"}');
    });

    RequestSequenceGenerator::setGenerator(static fn (): string => 'CUSTOM_SEQ_001');

    $client = createUnifiedClient($mockHttpClient);
    $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    parse_str($capturedOptions['body'], $bodyData);
    $encryptedJson = is_string($bodyData['json_data'] ?? null) ? $bodyData['json_data'] : '';
    $crypto = new TripleDESCrypto('123456789012345678901234');
    $message = json_decode($crypto->decrypt($encryptedJson), true);

    expect($message)->toBeArray()
        ->and($message['request_seq'] ?? null)->toBe('CUSTOM_SEQ_001');
});
