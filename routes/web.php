<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\PublicSite\LandingController;
use App\Http\Controllers\PublicSite\MenuController;
use App\Http\Controllers\PublicSite\SeoController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;


Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/cardapio/{slug}', [MenuController::class, 'show'])->name('menu.show');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.mercadopago');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::view('/app/{any?}', 'spa.dashboard')->where('any', '.*')->name('dashboard');
Route::view('/admin/{any?}', 'spa.admin')->where('any', '.*')->name('admin');
Route::view('/login', 'spa.dashboard')->name('login');
Route::view('/redefinir-senha/{token}', 'spa.dashboard')->name('password.reset');
