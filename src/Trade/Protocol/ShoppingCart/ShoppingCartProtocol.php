<?php

declare(strict_types=1);

namespace EasyUmsPay\Trade\Protocol\ShoppingCart;

use EasyUmsPay\Exceptions\RequestException;
use EasyUmsPay\Trade\Enums\PayScene;
use EasyUmsPay\Trade\Enums\TradeProtocol;
use EasyUmsPay\Trade\Exceptions\UnsupportedSceneException;
use EasyUmsPay\Trade\Protocol\AbstractProtocol;
use Throwable;

/**
 * 新购物车交易协议（/v1/inip/upsp/shop/*）
 */
final readonly class ShoppingCartProtocol extends AbstractProtocol
{
    public function protocol(): TradeProtocol
    {
        return TradeProtocol::ShoppingCart;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws Throwable
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
     * @throws Throwable
     */
    public function query(array $order, PayScene $scene): array
    {
        return $this->post(
            Endpoints::query($scene),
            Mapper::query($order, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function close(array $order, PayScene $scene): array
    {
        return $this->post(
            Endpoints::close($scene),
            Mapper::close($order, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws UnsupportedSceneException
     */
    public function refund(array $refund, PayScene $scene): array
    {
        return $this->post(
            Endpoints::refund($scene),
            Mapper::refund($refund, $scene, $this->config),
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws UnsupportedSceneException
     */
    public function refundQuery(array $refund, PayScene $scene): array
    {
        return $this->post(
            Endpoints::refundQuery($scene),
            Mapper::refundQuery($refund, $scene, $this->config),
        );
    }
}
