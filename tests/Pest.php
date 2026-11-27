<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/bootstrap.php';

function selfContractFixtureFilePath(string $filename): string
{
    return __DIR__ . '/fixtures/files/' . $filename;
}

function selfContractSamplePngBase64(): string
{
    return base64_encode(selfContractSamplePngBinary());
}

function selfContractSamplePngBinary(): string
{
    $binary = file_get_contents(selfContractFixtureFilePath('sample.png'));

    if (! is_string($binary)) {
        throw new RuntimeException('Failed to read sample PNG image');
    }

    return $binary;
}

function selfContractSamplePngDataUri(?string $base64 = null): string
{
    return 'data:image/png;base64,' . ($base64 ?? selfContractSamplePngBase64());
}

function selfContractSampleJpegBase64(): string
{
    return base64_encode(selfContractSampleJpegBinary());
}

function selfContractSampleJpegBinary(): string
{
    $binary = file_get_contents(selfContractFixtureFilePath('sample.jpg'));

    if (! is_string($binary)) {
        throw new RuntimeException('Failed to read sample JPEG image');
    }

    return $binary;
}

function selfContractSampleJpegDataUri(string $subtype = 'jpeg'): string
{
    return 'data:image/' . $subtype . ';base64,' . selfContractSampleJpegBase64();
}

function selfContractOversizedPngBinary(): string
{
    return selfContractSamplePngBinary() . str_repeat('A', 2 * 1024 * 1024);
}

function selfContractOversizedPngDataUri(): string
{
    return 'data:image/png;base64,' . base64_encode(selfContractOversizedPngBinary());
}

function selfContractOversizedPngBase64(): string
{
    return base64_encode(selfContractOversizedPngBinary());
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// 可在此添加自定义期望方法
