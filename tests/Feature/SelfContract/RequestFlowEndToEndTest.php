<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\SelfContract\Client;
use EasyUmsPay\SelfContract\Enums\DataType;
use EasyUmsPay\SelfContract\Supports\Image;
use EasyUmsPay\Supports\Config;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function createClient(MockHttpClient $mockHttpClient): Client
{
    $config = new Config([
        'self_contract' => [
            'accesser_id' => 'TEST_ID',
            'sign_key' => '123456789012345678901234',
            'base_url' => 'https://selfapply-test.chinaums.com',
        ],
    ]);

    return new Client($config, new HttpClient($mockHttpClient, new NullLogger));
}

test('完整POST请求流程工作正常', function () {
    $mockHttpClient = new MockHttpClient(
        new MockResponse(json_encode([
            'res_code' => '0000',
            'res_msg' => '上传成功',
            'file_path' => 'PIC123',
            'file_type' => 'png',
            'file_size' => '1024',
        ]) ?: '{}')
    );

    $client = createClient($mockHttpClient);
    $response = $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    expect($response['file_path'])->toBe('PIC123')
        ->and($response['file_type'])->toBe('png');
});

test('完整GET请求流程工作正常', function () {
    $mockHttpClient = new MockHttpClient(
        new MockResponse("province,city\n安徽,合肥\n")
    );

    $client = createClient($mockHttpClient);
    $content = $client->downloadData(DataType::Province);

    expect($content)->toBeString()
        ->and($content)->toContain('安徽');
});

test('业务错误响应返回数组', function () {
    $mockHttpClient = new MockHttpClient(
        new MockResponse(json_encode([
            'res_code' => '5014',
            'res_msg' => '参数不合法: base64图片编码不能为空',
        ]) ?: '{}')
    );

    $client = createClient($mockHttpClient);
    $response = $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    expect($response['res_code'])->toBe('5014')
        ->and($response['res_msg'])->toBe('参数不合法: base64图片编码不能为空');
});

test('HTTP错误被处理', function () {
    $mockHttpClient = new MockHttpClient(function () {
        throw new TransportException('Network error');
    });

    $client = createClient($mockHttpClient);

    expect(fn () => $client->uploadImage(Image::make(selfContractSamplePngBase64())))
        ->toThrow(RequestException::class);
});

test('合法图片数据被处理', function () {
    $mockHttpClient = new MockHttpClient(
        new MockResponse('{"res_code":"0000","res_msg":"成功"}')
    );

    $client = createClient($mockHttpClient);

    expect(fn () => $client->uploadImage(Image::make(selfContractSamplePngBase64())))
        ->not->toThrow(Exception::class);
});
