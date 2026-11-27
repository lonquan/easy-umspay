<?php

declare(strict_types=1);

use EasyUmsPay\Exceptions\ValidationException;
use EasyUmsPay\SelfContract\Supports\Image;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('接受本地 PNG 文件并转换为 data URI', function () {
    $image = Image::make(selfContractFixtureFilePath('sample.png'));

    expect($image->toDataUri())->toBe(selfContractSamplePngDataUri())
        ->and($image->getMimeType())->toBe('image/png')
        ->and($image->getSubtype())->toBe('png');
});

test('接受本地 JPEG 文件并转换为 data URI', function () {
    $image = Image::make(selfContractFixtureFilePath('sample.jpg'));

    expect($image->toDataUri())->toBe(selfContractSampleJpegDataUri())
        ->and($image->getMimeType())->toBe('image/jpeg')
        ->and($image->getSubtype())->toBe('jpeg');
});

test('本地文件按内容而不是扩展名识别类型', function () {
    selfContractImageTestWithTempFile(selfContractSamplePngBinary(), 'gif', function (string $path): void {
        $image = Image::make($path);

        expect($image->getSubtype())->toBe('png')
            ->and($image->getMimeType())->toBe('image/png')
            ->and($image->toDataUri())->toBe(selfContractSamplePngDataUri());
    });
});

test('接受远程 URL 图片并转换为 data URI', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(selfContractSamplePngBinary()),
    ]);

    $image = Image::make('https://example.com/image.gif', $httpClient);

    expect($image->toDataUri())->toBe(selfContractSamplePngDataUri())
        ->and($image->getMimeType())->toBe('image/png');
});

test('接受合法 JPEG base64 字符串并与构造函数行为一致', function () {
    $viaFactory = Image::make(selfContractSampleJpegBase64());
    $viaConstructor = new Image(selfContractSampleJpegBase64());

    expect($viaFactory->toDataUri())->toBe($viaConstructor->toDataUri())
        ->and($viaFactory->getMimeType())->toBe('image/jpeg')
        ->and($viaFactory->getSubtype())->toBe('jpeg')
        ->and($viaFactory->toDataUri())->toStartWith('data:image/jpeg;base64,');
});

test('接受合法 PNG base64 字符串并转换为 data URI', function () {
    $image = Image::make(selfContractSamplePngBase64());

    expect($image->toDataUri())->toBe(selfContractSamplePngDataUri())
        ->and($image->getMimeType())->toBe('image/png')
        ->and($image->getSubtype())->toBe('png');
});

test('拒绝空图片来源', function () {
    expect(fn () => Image::make(''))
        ->toThrow(ValidationException::class, '图片来源不能为空');
});

test('拒绝非图片 base64 字符串', function () {
    expect(fn () => Image::make(base64_encode('not an image')))
        ->toThrow(ValidationException::class, '仅支持 jpg、jpeg、png 图片');
});

test('拒绝非法 base64 图片数据', function () {
    expect(fn () => Image::make('iVBORw0K%%%'))
        ->toThrow(ValidationException::class, 'base64');
});

test('兼容 data URI 输入并信任前缀类型', function () {
    $image = Image::make('data:image/png;base64,' . selfContractSampleJpegBase64());

    expect($image->getSubtype())->toBe('png')
        ->and($image->getMimeType())->toBe('image/png')
        ->and($image->toDataUri())->toBe(selfContractSamplePngDataUri(selfContractSampleJpegBase64()));
});

test('兼容 data:image/jpg 前缀并归一化为 jpeg', function () {
    $image = Image::make(selfContractSampleJpegDataUri('jpg'));

    expect($image->getSubtype())->toBe('jpeg')
        ->and($image->getMimeType())->toBe('image/jpeg')
        ->and($image->toDataUri())->toBe(selfContractSampleJpegDataUri());
});

test('拒绝 data URI 中的换行符', function () {
    $dataUri = 'data:image/png;base64,' . substr(selfContractSamplePngBase64(), 0, 12) . "\n" . substr(selfContractSamplePngBase64(), 12);

    expect(fn () => Image::make($dataUri))
        ->toThrow(ValidationException::class, '换行符');
});

test('拒绝无法从内容识别的文件', function () {
    selfContractImageTestWithTempFile('not-an-image', 'png', function (string $path): void {
        expect(fn () => Image::make($path))
            ->toThrow(ValidationException::class, '仅支持 jpg、jpeg、png 图片');
    });
});

test('拒绝超过 2MB 的图片', function () {
    expect(fn () => Image::make(selfContractOversizedPngBase64()))
        ->toThrow(ValidationException::class, '2MB');
});

test('拒绝不可访问的 URL', function () {
    $httpClient = new MockHttpClient(function (): never {
        throw new TransportException('Connection refused');
    });

    expect(fn () => Image::make('https://example.com/not-found.png', $httpClient))
        ->toThrow(ValidationException::class, 'URL 下载失败');
});

test('拒绝不支持的协议', function () {
    expect(fn () => Image::make('file:///tmp/example.png'))
        ->toThrow(ValidationException::class, '图片来源无效或文件不可读');
});

function selfContractImageTestCreateTempFile(string $contents, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'easy-ums-image-');

    if ($path === false) {
        throw new RuntimeException('Unable to create temporary image file');
    }

    $targetPath = $path . '.' . $extension;
    rename($path, $targetPath);
    file_put_contents($targetPath, $contents);

    return $targetPath;
}

/**
 * @param  callable(string): void  $callback
 */
function selfContractImageTestWithTempFile(string $contents, string $extension, callable $callback): void
{
    $path = selfContractImageTestCreateTempFile($contents, $extension);

    try {
        $callback($path);
    } finally {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
