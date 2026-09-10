<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Policies\CategoryPolicy;
use App\Policies\EstablishmentPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const POLICIES = [
        Establishment::class => EstablishmentPolicy::class,
        Category::class => CategoryPolicy::class,
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Em desenvolvimento e teste, qualquer lazy loading vira excecao.
         * E a forma mais barata de impedir que um N+1 chegue em producao.
         */
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Autenticacao: janela curta e agressiva contra forca bruta.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinutes(15, 3)
            ->by('pwd:'.strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Checkout publico: evita flood de pedidos sem travar um restaurante movimentado.
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
