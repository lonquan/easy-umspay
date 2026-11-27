<?php

declare(strict_types=1);

namespace EasyUmsPay\SelfContract\Enums;

/**
 * 签约状态
 */
enum ApplyStatus: string
{
    case SIGNING = '00';              // 签约中
    case SIGN_SUCCESS = '01';         // 签约成功（中间状态）
    case AUDITING = '02';             // 入网审核中
    case SUCCESS = '03';              // 入网成功
    case FAILED = '04';               // 入网失败
    case COMPANY_VERIFY = '05';       // 对公账户待验证或异常
    case RISK_AUDIT = '06';           // 风控审核中
    case SMS_CONTRACT_SUCCESS = '11'; // 短信签生成合同成功
    case FILLING = '18';              // 资料填写中
    case VALIDATE_FAILED = '28';      // 资料验证失败
    case FROZEN = '31';               // 冻结账户
    case VIDEO_VERIFYING = '32';      // 客服视频核验中
    case VIDEO_FAILED = '33';         // 客服视频核验失败
    case WAIT_MANAGER = '34';         // 待客户经理补充材料
    case DM_FROZEN = '35';            // DM已冻结
    case OTHER_ERROR = '99';          // 其它错误

    public function label(): string
    {
        return match ($this) {
            self::SIGNING => '签约中',
            self::SIGN_SUCCESS => '签约成功',
            self::AUDITING => '入网审核中',
            self::SUCCESS => '入网成功',
            self::FAILED => '入网失败',
            self::COMPANY_VERIFY => '对公账户待验证或异常',
            self::RISK_AUDIT => '风控审核中',
            self::SMS_CONTRACT_SUCCESS => '短信签生成合同成功',
            self::FILLING => '资料填写中',
            self::VALIDATE_FAILED => '资料验证失败',
            self::FROZEN => '冻结账户',
            self::VIDEO_VERIFYING => '客服视频核验中',
            self::VIDEO_FAILED => '客服视频核验失败',
            self::WAIT_MANAGER => '待客户经理补充材料',
            self::DM_FROZEN => 'DM已冻结',
            self::OTHER_ERROR => '其它错误',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::VALIDATE_FAILED,
            self::VIDEO_FAILED,
            self::OTHER_ERROR,
        ]);
    }

    public function isPending(): bool
    {
        return in_array($this, [
            self::SIGNING,
            self::SIGN_SUCCESS,
            self::AUDITING,
            self::COMPANY_VERIFY,
            self::RISK_AUDIT,
            self::SMS_CONTRACT_SUCCESS,
            self::FILLING,
            self::VIDEO_VERIFYING,
            self::WAIT_MANAGER,
        ]);
    }
}
