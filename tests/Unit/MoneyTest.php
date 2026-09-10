<?php

declare(strict_types=1);

use App\Support\Money;

it('cria a partir de centavos', function (): void {
    expect(Money::fromCents(2990)->cents)->toBe(2990);
});

it('converte decimais em centavos sem erro de ponto flutuante', function (float|string $input, int $expected): void {
    expect(Money::fromDecimal($input)->cents)->toBe($expected);
})->with([
    [29.90, 2990],
    [0.1, 10],
    [0.07, 7],
    ['1234.56', 123456],
    ['29,90', 2990],
    ['1.234,56', 123456],
    [19.99, 1999],
    // O caso classico de arredondamento binario.
    [1.005, 101],
]);

it('rejeita valores negativos', function (): void {
    Money::fromDecimal(-1);
})->throws(InvalidArgumentException::class);

it('rejeita texto nao numerico', function (): void {
    Money::fromDecimal('abc');
})->throws(InvalidArgumentException::class);

it('soma e multiplica preservando centavos', function (): void {
    $price = Money::fromCents(2990);
    $addon = Money::fromCents(500);

    expect($price->plus($addon)->cents)->toBe(3490)
        ->and($price->plus($addon)->multipliedBy(3)->cents)->toBe(10470);
});

it('nunca produz total negativo na subtracao', function (): void {
    expect(Money::fromCents(100)->minus(Money::fromCents(500))->cents)->toBe(0);
});

it('recusa multiplicador negativo', function (): void {
    Money::fromCents(100)->multipliedBy(-2);
})->throws(InvalidArgumentException::class);

it('formata no padrao brasileiro', function (int $cents, string $expected): void {
    expect(Money::fromCents($cents)->format())->toBe($expected);
})->with([
    [2990, 'R$ 29,90'],
    [0, 'R$ 0,00'],
    [123456, 'R$ 1.234,56'],
    [100000000, 'R$ 1.000.000,00'],
    [5, 'R$ 0,05'],
]);

it('serializa em decimal para o gateway', function (): void {
    expect(Money::fromCents(2990)->toDecimal())->toBe(29.9)
        ->and(Money::fromCents(2990)->toDecimalString())->toBe('29.90')
        ->and(Money::fromCents(700)->toDecimalString())->toBe('7.00');
});

it('impede operar moedas diferentes', function (): void {
    Money::fromCents(100, 'BRL')->plus(Money::fromCents(100, 'USD'));
})->throws(InvalidArgumentException::class);
