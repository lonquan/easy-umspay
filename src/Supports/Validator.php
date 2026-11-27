<?php

declare(strict_types=1);

namespace EasyUmsPay\Supports;

class Validator
{
    /**
     * 验证身份证号
     *
     * @param  string  $idCard  身份证号
     * @return bool 是否有效
     */
    public static function validateIdCard(string $idCard): bool
    {
        // 18位，最后一位可能是X
        if (! preg_match('/^\d{17}[\dXx]$/', $idCard)) {
            return false;
        }

        // TODO: 可以添加更严格的校验码验证
        return true;
    }

    /**
     * 验证统一社会信用代码
     *
     * @param  string  $licenseNo  营业执照号
     * @return bool 是否有效
     */
    public static function validateLicenseNo(string $licenseNo): bool
    {
        // 18位统一社会信用代码
        // 不允许 I、O、Z、S、V 字符
        return (bool) preg_match(
            '/^[0-9A-HJ-NPQRTUWXY]{2}\d{6}[0-9A-HJ-NPQRTUWXY]{10}$/',
            $licenseNo
        );
    }

    /**
     * 验证手机号
     *
     * @param  string  $phone  手机号
     * @return bool 是否有效
     */
    public static function validatePhone(string $phone): bool
    {
        // 11位，1开头，第二位3-9
        return (bool) preg_match('/^1[3-9]\d{9}$/', $phone);
    }

    /**
     * 验证银行卡号
     *
     * @param  string  $card  银行卡号
     * @return bool 是否有效
     */
    public static function validateBankCard(string $card): bool
    {
        // 16-19位数字
        return (bool) preg_match('/^\d{16,19}$/', $card);
    }
}
