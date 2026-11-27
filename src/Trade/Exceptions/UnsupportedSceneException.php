<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Exceptions;

use EasyUmsPay\Exceptions\UmsException;

/**
 * 当前协议不支持该支付场景（如新购物车的 H5/APP）
 */
class UnsupportedSceneException extends UmsException
{
}
