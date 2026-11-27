<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\SelfContract\Client;
use EasyUmsPay\SelfContract\Enums\DataType;
use EasyUmsPay\SelfContract\Supports\Image;
use EasyUmsPay\Supports\Config;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function createSelfContractClient(MockHttpClient $mockHttpClient): Client
{
    return new Client(new Config([
        'self_contract' => [
            'base_url' => 'https://selfapply-test.chinaums.com',
            'accesser_id' => 'test_accesser',
            'sign_key' => '123456789012345678901234',
        ],
    ]), new HttpClient($mockHttpClient));
}

test('可以创建客户端', function () {
    expect(createSelfContractClient(new MockHttpClient(new MockResponse('{}'))))
        ->toBeInstanceOf(Client::class);
});

test('上传图片返回响应数组', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(json_encode([
        'res_code' => '0000',
        'res_msg' => 'success',
        'file_path' => 'PIC123456',
    ]) ?: '{}')));

    $response = $client->uploadImage(Image::make(selfContractSamplePngBase64()));

    expect($response['res_code'])->toBe('0000')
        ->and($response['file_path'])->toBe('PIC123456');
});

test('上传图片支持字符串输入', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(
        '{"res_code":"0000","res_msg":"success","file_path":"PIC123456"}'
    )));

    $response = $client->uploadImage(selfContractSamplePngBase64());

    expect($response['file_path'])->toBe('PIC123456');
});

test('上传图片支持本地路径字符串输入', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(
        '{"res_code":"0000","res_msg":"success","file_path":"PIC123456"}'
    )));

    $response = $client->uploadImage(selfContractFixtureFilePath('sample.png'));

    expect($response['res_code'])->toBe('0000');
});

test('上传图片支持 data URI 字符串输入', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(
        '{"res_code":"0000","res_msg":"success","file_path":"PIC123456"}'
    )));

    $response = $client->uploadImage(selfContractSamplePngDataUri());

    expect($response['res_code'])->toBe('0000');
});

test('上传图片字符串输入非法时抛出异常', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse('{}')));

    expect(fn () => $client->uploadImage(''))
        ->toThrow(ValidationException::class, '图片来源不能为空');
});

test('批量上传图片保留原键名并返回逐项数组', function () {
    $responses = new MockHttpClient([
        new MockResponse('{"res_code":"0000","res_msg":"success","file_path":"PIC1"}'),
        new MockResponse('{"res_code":"9001","res_msg":"参数错误"}'),
        new MockResponse('{"res_code":"0000","res_msg":"success","file_path":"PIC3"}'),
    ]);
    $client = createSelfContractClient($responses);

    $results = $client->uploadImages([
        'license' => Image::make(selfContractSamplePngBase64()),
        'id_front' => selfContractFixtureFilePath('sample.png'),
        'id_back' => selfContractSamplePngDataUri(),
    ]);

    $licenseResult = $results['license'];
    $idFrontResult = $results['id_front'];
    $idBackResult = $results['id_back'];

    expect($results)->toHaveKeys(['license', 'id_front', 'id_back']);
    expect($licenseResult['success'])->toBeTrue();

    if (! isset($licenseResult['response'])) {
        throw new RuntimeException('Expected successful batch upload response for license.');
    }

    expect($licenseResult['response']['file_path'])->toBe('PIC1');
    expect($idFrontResult['success'])->toBeTrue();

    if (! isset($idFrontResult['response'])) {
        throw new RuntimeException('Expected parsed batch response for id_front.');
    }

    expect($idFrontResult['response']['res_code'])->toBe('9001');
    expect($idBackResult['success'])->toBeTrue();

    if (! isset($idBackResult['response'])) {
        throw new RuntimeException('Expected successful batch upload response for id_back.');
    }

    expect($idBackResult['response']['file_path'])->toBe('PIC3');
});

test('批量上传空数组直接返回空', function () {
    $requests = 0;
    $client = createSelfContractClient(new MockHttpClient(function () use (&$requests) {
        $requests++;

        return new MockResponse('{"res_code":"0000"}');
    }));

    expect($client->uploadImages([]))->toBe([])
        ->and($requests)->toBe(0);
});

test('批量上传图片预处理失败时不会发送请求', function () {
    $requests = 0;
    $client = createSelfContractClient(new MockHttpClient(function () use (&$requests) {
        $requests++;

        return new MockResponse('{"res_code":"0000"}');
    }));

    expect(fn () => $client->uploadImages([
        'license' => selfContractSamplePngBase64(),
        'invalid' => '',
    ]))->toThrow(ValidationException::class, '图片来源不能为空');

    expect($requests)->toBe(0);
});

test('下载数据返回字符串', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse("province,city\n安徽,合肥\n")));

    $content = $client->downloadData(DataType::Province);

    expect($content)->toBe("province,city\n安徽,合肥\n");
});

test('使用有效签名验证通知', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse('{}')));

    $notificationData = [
        'apply_status' => '03',
        'ums_reg_id' => 'REG123',
    ];

    ksort($notificationData);
    $signString = json_encode($notificationData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $notificationData['sign'] = hash('sha256', $signString);

    expect($client->verifyNotification($notificationData))->toBeTrue();
});

test('使用无效签名验证通知', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse('{}')));

    expect($client->verifyNotification([
        'apply_status' => '03',
        'sign' => 'INVALID_SIGNATURE',
    ]))->toBeFalse();
});

test('调用通用接口时会添加服务名称', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(json_encode([
        'errCode' => '00',
        'errMsg' => '成功',
        'res_code' => '0000',
    ]) ?: '{}')));

    $response = $client->call('test_service', ['key' => 'value']);

    expect($response)->toBeArray()
        ->and($response['errCode'])->toBe('00')
        ->and($response['res_code'])->toBe('0000');
});

test('业务错误返回数组而不是抛异常', function () {
    $client = createSelfContractClient(new MockHttpClient(new MockResponse(
        '{"res_code":"9001","res_msg":"参数错误"}'
    )));

    $response = $client->uploadImage(selfContractSamplePngBase64());

    expect($response['res_code'])->toBe('9001')
        ->and($response['res_msg'])->toBe('参数错误');
});
