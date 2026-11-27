<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Supports;

use EasyUmsPay\Exceptions\ValidationException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Image
{
    private const int MAX_BYTES = 2 * 1024 * 1024;

    private string $base64Payload;

    private string $mimeType;

    private string $subtype;

    /**
     * 解析并规范化图片输入来源。
     *
     * @param  string  $source  本地路径、远程 URL、纯 Base64 或 data URI
     * @param  HttpClientInterface|null  $httpClient  可选的远程下载客户端
     *
     * @throws ValidationException
     */
    public function __construct(string $source, private readonly ?HttpClientInterface $httpClient = null)
    {
        $source = trim($source);

        if ($source === '') {
            throw new ValidationException('图片来源不能为空');
        }

        // 按最明确的来源优先分流，避免把路径或 URL 误判为普通 Base64。
        if ($this->isDataUri($source)) {
            $this->parseDataUri($source);

            return;
        }

        if ($this->isRemoteUrl($source)) {
            $this->parseBinary($this->fetchRemoteContents($source));

            return;
        }

        if ($this->hasUnsupportedScheme($source)) {
            throw new ValidationException('图片来源无效或文件不可读');
        }

        if (is_file($source) && is_readable($source)) {
            $contents = file_get_contents($source);

            if ($contents === false) {
                throw new ValidationException('图片来源无效或文件不可读');
            }

            $this->parseBinary($contents);

            return;
        }

        $this->parsePlainBase64($source);
    }

    /**
     * 使用工厂方式创建图片值对象。
     *
     * @param  string  $source  本地路径、远程 URL、纯 Base64 或 data URI
     * @param  HttpClientInterface|null  $httpClient  可选的远程下载客户端
     *
     * @throws ValidationException
     */
    public static function make(string $source, ?HttpClientInterface $httpClient = null): self
    {
        return new self($source, $httpClient);
    }

    /**
     * 输出统一格式的 data URI。
     */
    public function toDataUri(): string
    {
        return sprintf('data:image/%s;base64,%s', $this->subtype, $this->base64Payload);
    }

    /**
     * 获取标准 MIME 类型。
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * 获取归一化后的图片子类型。
     */
    public function getSubtype(): string
    {
        return $this->subtype;
    }

    /**
     * 校验显式 data:image 输入，并直接保留其 Base64 payload。
     *
     * @param  string  $source  完整 data URI
     *
     * @throws ValidationException
     */
    protected function parseDataUri(string $source): void
    {
        if (preg_match('/[\r\n]/', $source) === 1) {
            throw new ValidationException('data:image base64 数据不能包含换行符');
        }

        if (! preg_match('/^data:image\/(?<subtype>jpg|jpeg|png);base64,(?<payload>[A-Za-z0-9+\/]*={0,2})$/i', $source, $matches)) {
            throw new ValidationException('图片 base64 数据格式无效');
        }

        $payload = $matches['payload'];

        if (! $this->isValidBase64Payload($payload)) {
            throw new ValidationException('图片 base64 数据格式无效');
        }

        if ($this->calculateDecodedSize($payload) > self::MAX_BYTES) {
            throw new ValidationException('图片大小不能超过 2MB');
        }

        $subtype = $this->normalizeSubtype($matches['subtype']);

        // 显式 data:image 输入信任调用方声明的类型，只做格式和大小校验。
        $this->base64Payload = $payload;
        $this->subtype = $subtype;
        $this->mimeType = 'image/' . $subtype;
    }

    /**
     * 解析普通 Base64 图片字符串，并按真实内容识别类型。
     *
     * @param  string  $source  不带 data URI 前缀的 Base64 字符串
     *
     * @throws ValidationException
     */
    protected function parsePlainBase64(string $source): void
    {
        if (preg_match('/[\r\n]/', $source) === 1) {
            throw new ValidationException('图片 base64 数据格式无效');
        }

        // 先用常见文件头做快速过滤，减少无意义的解码与 fileinfo 检测。
        if (! $this->matchesKnownBase64Prefix($source)) {
            throw new ValidationException('仅支持 jpg、jpeg、png 图片');
        }

        if (! $this->isValidBase64Payload($source)) {
            throw new ValidationException('图片 base64 数据格式无效');
        }

        $contents = base64_decode($source, true);

        if ($contents === false) {
            throw new ValidationException('图片 base64 数据格式无效');
        }

        $this->parseBinary($contents);
    }

    /**
     * 从二进制内容中校验大小、识别 MIME，并转为标准 Base64 表达。
     *
     * @param  string  $contents  原始二进制图片内容
     *
     * @throws ValidationException
     */
    protected function parseBinary(string $contents): void
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new ValidationException('图片大小不能超过 2MB');
        }

        $mimeType = $this->detectMimeType($contents);
        $subtype = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpeg',
            default => throw new ValidationException('仅支持 jpg、jpeg、png 图片'),
        };

        $this->base64Payload = base64_encode($contents);
        $this->mimeType = $mimeType;
        $this->subtype = $subtype;
    }

    /**
     * 通过 Symfony HttpClient 下载远程图片内容。
     *
     * @param  string  $url  远程图片地址
     *
     * @throws ValidationException
     */
    protected function fetchRemoteContents(string $url): string
    {
        $client = $this->httpClient ?? HttpClient::create();

        try {
            $response = $client->request('GET', $url);

            return $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw new ValidationException('URL 下载失败: ' . $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * 使用 fileinfo 基于二进制内容识别图片 MIME。
     *
     * @param  string  $contents  原始二进制图片内容
     *
     * @throws ValidationException
     */
    protected function detectMimeType(string $contents): string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! is_string($mimeType) || $mimeType === '') {
            throw new ValidationException('仅支持 jpg、jpeg、png 图片');
        }

        return $mimeType;
    }

    /**
     * 判断输入是否为显式 data:image URI。
     */
    protected function isDataUri(string $source): bool
    {
        return str_starts_with(strtolower($source), 'data:image/');
    }

    /**
     * 判断输入是否为支持的远程图片地址。
     */
    protected function isRemoteUrl(string $source): bool
    {
        $scheme = parse_url($source, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * 识别不被允许的 URI scheme，避免误走本地文件或 Base64 分支。
     */
    protected function hasUnsupportedScheme(string $source): bool
    {
        $scheme = parse_url($source, PHP_URL_SCHEME);

        return is_string($scheme) && ! in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * 通过常见魔数对应的 Base64 前缀快速过滤候选图片。
     */
    protected function matchesKnownBase64Prefix(string $payload): bool
    {
        return str_starts_with($payload, 'iVBORw0K') || str_starts_with($payload, '/9j/');
    }

    /**
     * 校验 Base64 payload 的字符集与分组结构。
     */
    protected function isValidBase64Payload(string $payload): bool
    {
        if ($payload === '' || strlen($payload) % 4 !== 0) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $payload) === 1;
    }

    /**
     * 根据 Base64 长度推导解码后的原始字节数。
     */
    protected function calculateDecodedSize(string $payload): int
    {
        $padding = 0;

        if (str_ends_with($payload, '==')) {
            $padding = 2;
        } elseif (str_ends_with($payload, '=')) {
            $padding = 1;
        }

        // Base64 解码后大小公式：(length * 3 / 4) - padding。
        return intdiv(strlen($payload) * 3, 4) - $padding;
    }

    /**
     * 统一将 jpg 子类型归一化为 jpeg。
     */
    protected function normalizeSubtype(string $subtype): string
    {
        return strtolower($subtype) === 'jpg' ? 'jpeg' : strtolower($subtype);
    }
}
