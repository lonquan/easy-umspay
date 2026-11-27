<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol;

use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\TradeProtocol;

interface ProtocolInterface
{
    public function protocol(): TradeProtocol;

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public function pay(array $order, PayScene $scene): array;

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public function query(array $order, PayScene $scene): array;

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public function close(array $order, PayScene $scene): array;

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    public function refund(array $refund, PayScene $scene): array;

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    public function refundQuery(array $refund, PayScene $scene): array;
}
