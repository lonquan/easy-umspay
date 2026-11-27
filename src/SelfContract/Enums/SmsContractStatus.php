<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 短信签约合同状态
 */
enum SmsContractStatus: string
{
    case GENERATE_SUCCESS = '100';          // 生成合同成功
    case GENERATE_FAILED = '101';           // 生成合同失败
    case SEND_SUCCESS = '102';              // 发送合同成功
    case SEND_FAILED = '103';               // 发送合同失败
    case SIGN_SUCCESS = '104';              // 签署合同成功
    case SIGN_FAILED = '106';               // 签署合同失败
    case PARTY_A_SIGN_SUCCESS = '107';      // 甲方签署合同成功
    case PARTY_A_SIGN_FAILED = '108';       // 甲方签署合同失败
    case MARK_EFFECTIVE_SUCCESS = '109';    // 标记合同生效成功
    case MARK_EFFECTIVE_FAILED = '110';     // 标记合同生效失败
    case REVOKE_SUCCESS = '111';            // 撤销合同成功
    case REVOKE_FAILED = '112';             // 撤销合同失败
    case SIGN_TIMEOUT = '113';              // 签署合同超时

    public function label(): string
    {
        return match ($this) {
            self::GENERATE_SUCCESS => '生成合同成功',
            self::GENERATE_FAILED => '生成合同失败',
            self::SEND_SUCCESS => '发送合同成功',
            self::SEND_FAILED => '发送合同失败',
            self::SIGN_SUCCESS => '签署合同成功',
            self::SIGN_FAILED => '签署合同失败',
            self::PARTY_A_SIGN_SUCCESS => '甲方签署合同成功',
            self::PARTY_A_SIGN_FAILED => '甲方签署合同失败',
            self::MARK_EFFECTIVE_SUCCESS => '标记合同生效成功',
            self::MARK_EFFECTIVE_FAILED => '标记合同生效失败',
            self::REVOKE_SUCCESS => '撤销合同成功',
            self::REVOKE_FAILED => '撤销合同失败',
            self::SIGN_TIMEOUT => '签署合同超时',
        };
    }

    public function isSuccess(): bool
    {
        return in_array($this, [
            self::GENERATE_SUCCESS,
            self::SEND_SUCCESS,
            self::SIGN_SUCCESS,
            self::PARTY_A_SIGN_SUCCESS,
            self::MARK_EFFECTIVE_SUCCESS,
            self::REVOKE_SUCCESS,
        ]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [
            self::GENERATE_FAILED,
            self::SEND_FAILED,
            self::SIGN_FAILED,
            self::PARTY_A_SIGN_FAILED,
            self::MARK_EFFECTIVE_FAILED,
            self::REVOKE_FAILED,
        ]);
    }

    public function isTimeout(): bool
    {
        return $this === self::SIGN_TIMEOUT;
    }
}
