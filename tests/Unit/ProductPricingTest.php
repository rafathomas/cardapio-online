<?php

declare(strict_types=1);

use App\Models\Product;

it('usa o preco cheio sem promocao', function (): void {
    $product = new Product(['price_cents' => 2990]);

    expect($product->effectivePrice()->cents)->toBe(2990)
        ->and($product->hasDiscount())->toBeFalse()
        ->and($product->discountPercentage())->toBe(0);
});

it('usa a promocao quando ela e menor', function (): void {
    $product = new Product(['price_cents' => 2990, 'promo_price_cents' => 1990]);

    expect($product->effectivePrice()->cents)->toBe(1990)
        ->and($product->hasDiscount())->toBeTrue()
        ->and($product->discountPercentage())->toBe(33);
});

it('ignora promocao igual ou maior que o preco cheio', function (int $price, int $promo): void {
    $product = new Product(['price_cents' => $price, 'promo_price_cents' => $promo]);

    expect($product->effectivePrice()->cents)->toBe($price)
        ->and($product->hasDiscount())->toBeFalse();
})->with([
    [2990, 2990],
    [2990, 3500],
]);

it('calcula o percentual de desconto', function (int $price, int $promo, int $expected): void {
    $product = new Product(['price_cents' => $price, 'promo_price_cents' => $promo]);

    expect($product->discountPercentage())->toBe($expected);
})->with([
    [10000, 5000, 50],
    [10000, 9000, 10],
    [2990, 1990, 33],
]);

it('nao divide por zero em produto gratuito', function (): void {
    $product = new Product(['price_cents' => 0, 'promo_price_cents' => 0]);

    expect($product->discountPercentage())->toBe(0);
});
