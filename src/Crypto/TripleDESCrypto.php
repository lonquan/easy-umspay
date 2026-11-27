<?php

declare(strict_types=1);

namespace EasyUmsPay\Crypto;

use EasyUmsPay\Exceptions\CryptoException;

/**
 * TripleDES 加密类（使用 ECB 模式，PKCS5Padding）
 *
 * 用于自助签约接口的敏感字段加密
 * 加密算法：DESede/ECB/PKCS5Padding
 * ECB 模式不需要初始化向量（IV）
 */
readonly class TripleDESCrypto
{
    protected const string CIPHER_METHOD = 'DES-EDE3-ECB';

    /**
     * @throws CryptoException
     */
    public function __construct(protected string $key)
    {
        if (strlen($key) !== 24) {
            throw new CryptoException('3DES key must be 24 bytes');
        }
    }

    /**
     * 加密数据
     *
     * @param  string  $plaintext  明文
     * @return string 十六进制编码的密文
     *
     * @throws CryptoException
     */
    public function encrypt(string $plaintext): string
    {
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
        );

        if ($encrypted === false) {
            throw new CryptoException('Encryption failed');
        }

        return bin2hex($encrypted);
    }

    /**
     * 解密数据
     *
     * @param  string  $ciphertext  十六进制编码的密文
     * @return string 明文
     *
     * @throws CryptoException
     */
    public function decrypt(string $ciphertext): string
    {
        // 验证输入是否为有效的十六进制字符串
        if (! ctype_xdigit($ciphertext)) {
            throw new CryptoException('Decryption failed');
        }

        $decoded = hex2bin($ciphertext);

        if ($decoded === false) {
            throw new CryptoException('Decryption failed');
        }

        $decrypted = openssl_decrypt(
            $decoded,
            self::CIPHER_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
        );

        if ($decrypted === false) {
            throw new CryptoException('Decryption failed');
        }

        return $decrypted;
    }
}
