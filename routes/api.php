<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminEstablishmentController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\AddonGroupController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EstablishmentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\PublicSite\PublicOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas publicas
|--------------------------------------------------------------------------
*/

Route::get('plans', [PlanController::class, 'index'])->name('api.plans.index');

Route::prefix('auth')->group(function (): void {
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('api.auth.register');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('api.auth.login');

    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:password-reset')
        ->name('api.auth.forgot');

    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('api.auth.reset');
});

// Carrinho e pedido do consumidor final: sem autenticacao, com rate limit.
Route::prefix('menu/{slug}')->middleware('throttle:orders')->group(function (): void {
    Route::post('cart/preview', [PublicOrderController::class, 'preview'])->name('api.menu.cart.preview');
    Route::post('orders', [PublicOrderController::class, 'store'])->name('api.menu.orders.store');
});

/*
|--------------------------------------------------------------------------
| Painel do cliente
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'not.blocked'])->group(function (): void {
    Route::post('auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.auth.logout');
    Route::post('auth/email/resend', [RegisteredUserController::class, 'resendVerification'])
        ->middleware('throttle:password-reset')
        ->name('api.auth.email.resend');

    Route::get('me', [ProfileController::class, 'show'])->name('api.me');
    Route::put('me', [ProfileController::class, 'update'])->name('api.me.update');
    Route::put('me/password', [ProfileController::class, 'updatePassword'])->name('api.me.password');
    Route::delete('me', [ProfileController::class, 'destroy'])->name('api.me.destroy');

    Route::get('establishments', [EstablishmentController::class, 'index'])->name('api.establishments.index');
    Route::post('establishments', [EstablishmentController::class, 'store'])->name('api.establishments.store');

    Route::prefix('establishments/{establishment:id}')->group(function (): void {
        Route::get('/', [EstablishmentController::class, 'show'])->name('api.establishments.show');
        Route::match(['put', 'post'], '/', [EstablishmentController::class, 'update'])->name('api.establishments.update');
        Route::delete('/', [EstablishmentController::class, 'destroy'])->name('api.establishments.destroy');
        Route::post('publish', [EstablishmentController::class, 'publish'])->name('api.establishments.publish');
        Route::post('unpublish', [EstablishmentController::class, 'unpublish'])->name('api.establishments.unpublish');
        Route::put('business-hours', [EstablishmentController::class, 'businessHours'])->name('api.establishments.hours');

        Route::get('dashboard', [DashboardController::class, 'show'])->name('api.dashboard');

        // Categorias
        Route::get('categories', [CategoryController::class, 'index'])->name('api.categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('api.categories.store');
        Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('api.categories.reorder');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('api.categories.show');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');

        // Produtos
        Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
        Route::post('products', [ProductController::class, 'store'])->name('api.products.store');
        Route::post('products/import', [ProductController::class, 'import'])->name('api.products.import');
        Route::post('products/reorder', [ProductController::class, 'reorder'])->name('api.products.reorder');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('api.products.show');
        Route::match(['put', 'post'], 'products/{product}', [ProductController::class, 'update'])->name('api.products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('api.products.destroy');
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('api.products.duplicate');
        Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('api.products.toggle');

        // Adicionais
        Route::get('addon-groups', [AddonGroupController::class, 'index'])->name('api.addons.index');
        Route::post('addon-groups', [AddonGroupController::class, 'store'])->name('api.addons.store');
        Route::put('addon-groups/{addonGroup}', [AddonGroupController::class, 'update'])->name('api.addons.update');
        Route::delete('addon-groups/{addonGroup}', [AddonGroupController::class, 'destroy'])->name('api.addons.destroy');

        // Pedidos
        Route::get('orders', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('api.orders.status');

        // QR Code
        Route::get('qrcode', [QrCodeController::class, 'show'])->name('api.qrcode.show');
        Route::post('qrcode/regenerate', [QrCodeController::class, 'regenerate'])->name('api.qrcode.regenerate');
        Route::get('qrcode/download', [QrCodeController::class, 'download'])->name('api.qrcode.download');

        // Assinatura
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('api.subscription.show');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->name('api.subscription.checkout');
        Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])->name('api.subscription.cancel');
        Route::post('payments/{payment}/sync', [SubscriptionController::class, 'syncPayment'])->name('api.payments.sync');
    });
});

/*
|--------------------------------------------------------------------------
| Painel da plataforma (administrador)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'not.blocked', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('metrics', [AdminBillingController::class, 'metrics'])->name('api.admin.metrics');

    Route::get('users', [AdminUserController::class, 'index'])->name('api.admin.users');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('api.admin.users.show');
    Route::post('users/{user}/block', [AdminUserController::class, 'block'])->name('api.admin.users.block');
    Route::post('users/{user}/unblock', [AdminUserController::class, 'unblock'])->name('api.admin.users.unblock');

    Route::get('establishments', [AdminEstablishmentController::class, 'index'])->name('api.admin.establishments');
    Route::get('establishments/{establishment:id}', [AdminEstablishmentController::class, 'show'])->name('api.admin.establishments.show');

    Route::get('plans', [AdminBillingController::class, 'plans'])->name('api.admin.plans');
    Route::put('plans/{plan}', [AdminBillingController::class, 'updatePlan'])->name('api.admin.plans.update');

    Route::get('subscriptions', [AdminBillingController::class, 'subscriptions'])->name('api.admin.subscriptions');
    Route::post('subscriptions/{subscription}/cancel', [AdminBillingController::class, 'cancelSubscription'])
        ->name('api.admin.subscriptions.cancel');

    Route::get('payments', [AdminBillingController::class, 'payments'])->name('api.admin.payments');
    Route::get('payment-events', [AdminBillingController::class, 'paymentEvents'])->name('api.admin.payment-events');
});
