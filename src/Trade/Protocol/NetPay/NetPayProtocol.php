<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol\NetPay;

use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\TradeProtocol;
use EasyUmsPay\Trade\Protocol\AbstractProtocol;

/**
 * 老购物车交易协议（/v1/netpay/*）
 */
final readonly class NetPayProtocol extends AbstractProtocol
{
    public function protocol(): TradeProtocol
    {
        return TradeProtocol::NetPay;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function pay(array $order, PayScene $scene): array
    {
        return $this->post(
            Endpoints::pay($scene),
            Mapper::pay($order, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function query(array $order, PayScene $scene): array
    {
        return $this->post(
            Endpoints::query(),
            Mapper::query($order, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function close(array $order, PayScene $scene): array
    {
        return $this->post(
            Endpoints::close(),
            Mapper::close($order, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function refund(array $refund, PayScene $scene): array
    {
        return $this->post(
            Endpoints::refund(),
            Mapper::refund($refund, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function refundQuery(array $refund, PayScene $scene): array
    {
        return $this->post(
            Endpoints::refundQuery(),
            Mapper::refundQuery($refund, $scene, $this->config),
        );
    }
}
