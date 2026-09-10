<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Billing\Contracts\PaymentGatewayInterface;
use App\Services\Billing\Gateways\MercadoPagoGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Resolve o gateway ativo. Adicionar um provedor novo significa registrar
 * outra classe neste mapa — nenhuma outra parte do sistema muda.
 */
class PaymentServiceProvider extends ServiceProvider
{
    private const GATEWAYS = [
        'mercadopago' => MercadoPagoGateway::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            $driver = (string) config('cardapio.payment_gateway', 'mercadopago');

            if (! isset(self::GATEWAYS[$driver])) {
                throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$driver}");
            }

            return $app->make(self::GATEWAYS[$driver]);
        });
    }
}
