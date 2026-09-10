<?php

declare(strict_types=1);

use App\Models\Establishment;
use App\Services\SlugGenerator;

beforeEach(function (): void {
    $this->slugs = new SlugGenerator;
});

it('normaliza acentos e simbolos', function (string $input, string $expected): void {
    expect($this->slugs->normalize($input))->toBe($expected);
})->with([
    ['Minha Lanchonete', 'minha-lanchonete'],
    ['Açaí & Cia', 'acai-cia'],
    ['Pizzaria  do   João', 'pizzaria-do-joao'],
    ['CAFÉ 100% Arábica', 'cafe-100-arabica'],
]);

it('gera um identificador utilizavel quando a entrada nao produz slug', function (): void {
    expect($this->slugs->normalize('!!!'))->toStartWith('estabelecimento-');
});

it('acrescenta sufixo numerico quando o slug ja existe', function (): void {
    Establishment::factory()->create(['slug' => 'minha-lanchonete']);

    $slug = $this->slugs->unique(
        'Minha Lanchonete',
        fn (string $candidate) => Establishment::query()->where('slug', $candidate),
    );

    expect($slug)->toBe('minha-lanchonete-2');
});

it('continua incrementando ate achar um slug livre', function (): void {
    Establishment::factory()->create(['slug' => 'burger']);
    Establishment::factory()->create(['slug' => 'burger-2']);
    Establishment::factory()->create(['slug' => 'burger-3']);

    $slug = $this->slugs->unique(
        'Burger',
        fn (string $candidate) => Establishment::query()->where('slug', $candidate),
    );

    expect($slug)->toBe('burger-4');
});

it('recusa slugs reservados da plataforma', function (): void {
    $slug = $this->slugs->unique(
        'admin',
        fn (string $candidate) => Establishment::query()->where('slug', $candidate),
        ['admin', 'app'],
    );

    expect($slug)->toBe('admin-2');
});

it('ignora o proprio registro ao atualizar', function (): void {
    $establishment = Establishment::factory()->create(['slug' => 'pizzaria']);

    $slug = $this->slugs->unique(
        'Pizzaria',
        fn (string $candidate) => Establishment::query()->where('slug', $candidate),
        ignoreId: $establishment->id,
    );

    expect($slug)->toBe('pizzaria');
});
