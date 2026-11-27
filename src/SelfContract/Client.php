<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract;

use EasyUmsPay\Crypto\Sha256Signer;
use EasyUmsPay\Crypto\TripleDESCrypto;
use EasyUmsPay\Emuns\HttpMethod;
use EasyUmsPay\Exceptions\CryptoException;
use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Http\HttpClient;
use EasyUmsPay\Http\Response;
use EasyUmsPay\SelfContract\Enums\DataType;
use EasyUmsPay\SelfContract\Exceptions\SignatureException;
use EasyUmsPay\SelfContract\Supports\Image;
use EasyUmsPay\SelfContract\Supports\MessageBuilder;
use EasyUmsPay\SelfContract\Supports\RequestSequenceGenerator;
use EasyUmsPay\Supports\Config;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

readonly class Client
{
    /** @var array<string, mixed> */
    protected array $selfContractConfig;

    protected string $accesserId;

    protected TripleDESCrypto $crypto;

    protected Sha256Signer $signer;

    protected string $baseUrl;

    protected HttpClient $httpClient;

    protected RequestSequenceGenerator $sequenceGenerator;

    protected MessageBuilder $messageBuilder;

    /**
     * @throws CryptoException
     */
    public function __construct(Config $config, HttpClient $httpClient)
    {
        $this->selfContractConfig = $config->getSelfContract();
        $this->accesserId = (string) ($this->selfContractConfig['accesser_id'] ?? '');
        $this->baseUrl = rtrim((string) ($this->selfContractConfig['base_url'] ?? ''), '/');
        $this->httpClient = $httpClient;

        $signKey = (string) ($this->selfContractConfig['sign_key'] ?? '');

        $this->crypto = new TripleDESCrypto($signKey);
        $this->signer = new Sha256Signer;

        $this->sequenceGenerator = new RequestSequenceGenerator;
        $this->messageBuilder = new MessageBuilder(
            $this->sequenceGenerator,
            $this->accesserId,
        );
    }

    /**
     * 所属支行查询接口
     *
     * 查询相应关键字的支行列表，包含支行名称和开户行行号
     *
     * @param  array<string, mixed>  $data  附加请求参数
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function queryBanks(string|int $code, string $key, array $data = []): array
    {
        return $this->call('branch_bank_list', array_replace([
            'areaCode' => $code,
            'key' => $key,
        ], $data));
    }

    /**
     * 后台图片上传接口
     *
     * 用于上传商户入网所需的各类证照图片（营业执照、身份证等）
     *
     * 图片要求：
     * - 格式：jpg, jpeg, png
     * - 大小：不超过2MB
     * - 尺寸：不限制
     *
     * @param  string|Image  $image  图片值对象或图片来源字符串，支持本地路径、URL 或 base64 字符串
     * @param  array<string, mixed>  $data  附加请求参数
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function uploadImage(string|Image $image, array $data = []): array
    {
        $image = $this->normalizeImage($image);

        $message = $this->buildRequestMessage('pic_upload', array_replace([
            'pic_base64' => $image->toDataUri(),
        ], $data));

        return $this->sendPostRequest($this->encryptAndSign($message));
    }

    /**
     * 批量上传图片。
     *
     * @param  array<int|string, string|Image>  $images
     * @param  array<string, mixed>  $data  附加请求参数，对每张图片生效
     * @return array<int|string, array{success: bool, response?: array<string, mixed>, error?: Throwable}>
     *
     * @throws Throwable
     */
    public function uploadImages(array $images, array $data = []): array
    {
        if ($images === []) {
            return [];
        }

        /** @var array<int|string, Image> $normalizedImages */
        $normalizedImages = [];

        foreach ($images as $key => $image) {
            $normalizedImages[$key] = $image instanceof Image ? $image : Image::make($image);
        }

        $endpoint = $this->getUnifiedEndpoint();

        /** @var array<int|string, array<string, mixed>> $payloads */
        $payloads = [];

        foreach ($normalizedImages as $key => $image) {
            $message = $this->buildRequestMessage('pic_upload', array_replace([
                'pic_base64' => $image->toDataUri(),
            ], $data));
            $payloads[$key] = $this->encryptAndSign($message);
        }

        $httpResults = $this->httpClient->batchPostForm($endpoint, $payloads);

        /** @var array<int|string, array{success: bool, response?: array<string, mixed>, error?: Throwable}> $results */
        $results = [];

        foreach ($httpResults as $key => $result) {
            if (! $result['success']) {
                $error = $result['error'] ?? new RequestException(
                    'Batch upload failed without error detail',
                    null,
                    ['key' => $key],
                );

                $results[$key] = ['success' => false, 'error' => $error];

                continue;
            }

            try {
                $response = $result['response'] ?? throw new RequestException(
                    'Batch upload response missing',
                    null,
                    ['key' => $key],
                );
                $results[$key] = [
                    'success' => true,
                    'response' => $this->parseResponse($response),
                ];
            } catch (Throwable $exception) {
                $results[$key] = ['success' => false, 'error' => $exception];
            }
        }

        return $results;
    }

    /**
     * 省市区行业数据下载接口
     *
     * 说明：
     * 获取最新的省市区以及行业分类（MCC）数据列表。
     * 此接口使用GET方式，返回CSV格式的数据列表供前端选择使用。
     *
     * @param  DataType  $dataType  文件类型
     * @param  array<string, mixed>  $data  附加请求参数
     * @return string 响应文本（通常是 CSV）
     *
     * @throws Throwable
     */
    public function downloadData(DataType $dataType, array $data = []): string
    {
        $message = $this->buildRequestMessage('data_download', array_replace([
            'data_type' => $dataType->value,
        ], $data));
        $encrypted = $this->encryptAndSign($message);

        $response = $this->httpClient->get($this->getUnifiedEndpoint(), $encrypted);
        $content = $response->getContent(false);

        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
            $content = $converted !== false ? $converted : $content;
        }

        return $content;
    }

    /**
     * 详细采集档案资料上传接口
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function applyContract(array $data): array
    {
        return $this->call('complex_upload', $data);
    }

    /**
     * 通用接口调用方法
     *
     * 说明：
     * 用于调用其他未单独实现的接口，此方法会自动处理加密、签名等通用逻辑。
     *
     * @param  string  $service  服务名称（接口的service字段值）
     * @param  array<string, mixed>  $data  请求数据（根据具体接口文档填写）
     * @param  string  $method  HTTP方法（GET/POST），默认POST
     * @return array<string, mixed> 响应数据（原始数组格式）
     *
     * @throws Throwable
     */
    public function call(string $service, array $data, string $method = HttpMethod::POST->value): array
    {
        $message = $this->buildRequestMessage($service, $data);
        $encrypted = $this->encryptAndSign($message);

        return ($method === HttpMethod::GET->value)
            ? $this->sendGetRequest($encrypted)
            : $this->sendPostRequest($encrypted);
    }

    /**
     * 验证异步推送通知（Webhook）
     *
     * 说明：
     * 银联会在入网审核完成后，通过定时推送方式通知平台方。
     * 使用本方法验证推送签名的真实性。
     *
     * @param  array<string, mixed>  $notificationData  推送的数据
     * @return bool 签名是否有效
     */
    public function verifyNotification(array $notificationData): bool
    {
        return $this->verifyResponseSignature($notificationData);
    }

    /**
     * 构建请求报文
     *
     * @param  string  $service  服务名称
     * @param  array<string, mixed>  $data  业务数据
     * @return array<string, mixed> 完整报文
     *
     * @throws Throwable
     */
    protected function buildRequestMessage(string $service, array $data): array
    {
        return $this->messageBuilder->build($service, $data);
    }

    /**
     * 对报文进行加密和签名
     *
     * 签名和加密均基于明文报文独立进行
     *
     * @param  array<string, mixed>  $message  明文报文
     * @return array{json_data: string, sign_data: string, accesser_id: string}
     *
     * @throws CryptoException 加密失败
     */
    protected function encryptAndSign(array $message): array
    {
        $plaintext = json_encode(
            $message,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if ($plaintext === false) {
            throw new CryptoException('JSON encoding failed');
        }

        $signData = $this->signer->sign($plaintext);
        $jsonData = $this->crypto->encrypt($plaintext);

        $encryptedParams = [
            'json_data' => $jsonData,
            'sign_data' => $signData,
            'accesser_id' => $this->accesserId,
        ];

        return $encryptedParams;
    }

    /**
     * 发送 POST 请求到统一端点
     *
     * @param  array<string, mixed>  $data  请求数据（已加密签名的三参数）
     * @return array<string, mixed> 响应数据
     *
     * @throws Throwable
     */
    protected function sendPostRequest(array $data): array
    {
        $response = $this->httpClient->post(
            $this->getUnifiedEndpoint(),
            $data,
        );

        return $this->parseResponse($response);
    }

    /**
     * 发送 GET 请求到统一端点
     *
     * @param  array<string, mixed>  $data  请求数据（已加密签名的三参数）
     * @return array<string, mixed> 响应数据
     *
     * @throws Throwable
     */
    protected function sendGetRequest(array $data): array
    {
        $response = $this->httpClient->get(
            $this->getUnifiedEndpoint(),
            $data,
        );

        return $this->parseResponse($response);
    }

    protected function getUnifiedEndpoint(): string
    {
        return $this->baseUrl . '/self-contract-nmrs/interface/autoReg';
    }

    /**
     * 解析响应数据；业务错误由调用方检查 res_code，这里只处理 JSON 与验签。
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws SignatureException
     * @throws TransportExceptionInterface
     */
    protected function parseResponse(Response $response): array
    {
        $content = $response->getContent(false);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new RequestException(
                'Invalid JSON response',
                null,
                ['content' => $content],
            );
        }

        if (isset($data['sign']) && ! $this->verifyResponseSignature($data)) {
            throw new SignatureException(
                'Response signature verification failed',
                'SIGNATURE_ERROR',
                $data,
            );
        }

        return $data;
    }

    /**
     * 验证响应签名
     *
     * @param  array<string, mixed>  $data  响应数据（包含sign字段）
     * @return bool 验证是否通过
     */
    protected function verifyResponseSignature(array $data): bool
    {
        $sign = $data['sign'] ?? '';

        if (empty($sign)) {
            return true;
        }

        $dataWithoutSign = $data;
        unset($dataWithoutSign['sign']);

        ksort($dataWithoutSign);
        $signString = json_encode(
            $dataWithoutSign,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if ($signString === false) {
            return false;
        }

        $expectedSign = $this->signer->sign($signString);

        return hash_equals($expectedSign, $sign);
    }

    /**
     * @throws Throwable
     */
    protected function normalizeImage(string|Image $image): Image
    {
        return $image instanceof Image ? $image : Image::make($image);
    }
}
