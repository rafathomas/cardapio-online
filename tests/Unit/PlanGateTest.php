<?php

declare(strict_types=1);

use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Category;
use App\Models\Product;
use App\Services\Plans\PlanGate;

beforeEach(function (): void {
    $this->gate = app(PlanGate::class);
});

it('resolve o plano free como padrao', function (): void {
    $establishment = makeEstablishment();

    expect($this->gate->planFor($establishment)->slug)->toBe('free');
});

it('resolve o plano pro quando a assinatura esta ativa', function (): void {
    $establishment = makeEstablishment(proPlan());

    expect($this->gate->planFor($establishment)->slug)->toBe('pro');
});

it('rebaixa para o plano padrao quando a assinatura expira', function (): void {
    $establishment = makeEstablishment(proPlan());

    $establishment->subscription->update([
        'current_period_end' => now()->subDay(),
    ]);

    expect($this->gate->planFor($establishment->refresh())->slug)->toBe('free');
});

it('rebaixa para o plano padrao quando a assinatura e cancelada', function (): void {
    $establishment = makeEstablishment(proPlan());
    $establishment->subscription->update(['status' => SubscriptionStatus::Canceled]);

    expect($this->gate->planFor($establishment->refresh())->slug)->toBe('free');
});

it('calcula os produtos restantes no plano free', function (): void {
    $establishment = makeEstablishment();
    Product::factory()->count(4)->create(['establishment_id' => $establishment->id]);

    expect($this->gate->remainingProducts($establishment))->toBe(6);
});

it('trata plano pro como ilimitado', function (): void {
    $establishment = makeEstablishment(proPlan());
    Product::factory()->count(30)->create(['establishment_id' => $establishment->id]);

    $this->gate->assertCanCreateProduct($establishment);

    expect($this->gate->remainingProducts($establishment))->toBeNull();
});

it('bloqueia o 11o produto no plano free', function (): void {
    $establishment = makeEstablishment();
    Product::factory()->count(10)->create(['establishment_id' => $establishment->id]);

    $this->gate->assertCanCreateProduct($establishment);
})->throws(PlanLimitExceededException::class);

it('permite exatamente o limite do plano free', function (): void {
    $establishment = makeEstablishment();
    Product::factory()->count(9)->create(['establishment_id' => $establishment->id]);

    $this->gate->assertCanCreateProduct($establishment);

    expect($this->gate->remainingProducts($establishment))->toBe(1);
});

it('bloqueia categorias acima do limite', function (): void {
    $establishment = makeEstablishment();
    Category::factory()->count(5)->create(['establishment_id' => $establishment->id]);

    $this->gate->assertCanCreateCategory($establishment);
})->throws(PlanLimitExceededException::class);

it('libera recursos pro apenas para quem assina', function (): void {
    $free = makeEstablishment();
    $pro = makeEstablishment(proPlan());

    expect($this->gate->hasFeature($free, PlanFeature::RemoveBranding))->toBeFalse()
        ->and($this->gate->hasFeature($pro, PlanFeature::RemoveBranding))->toBeTrue()
        ->and($this->gate->hasFeature($free, PlanFeature::Statistics))->toBeFalse()
        ->and($this->gate->hasFeature($pro, PlanFeature::Statistics))->toBeTrue();
});

it('lanca excecao ao exigir recurso indisponivel', function (): void {
    $this->gate->assertHasFeature(makeEstablishment(), PlanFeature::Customization);
})->throws(PlanLimitExceededException::class);

it('resume limites e uso para o painel', function (): void {
    $establishment = makeEstablishment();
    Product::factory()->count(3)->create(['establishment_id' => $establishment->id]);
    Category::factory()->count(2)->create(['establishment_id' => $establishment->id]);

    $summary = $this->gate->summary($establishment);

    expect($summary['plan']['slug'])->toBe('free')
        ->and($summary['limits']['products_used'])->toBe(3)
        ->and($summary['limits']['products_remaining'])->toBe(7)
        ->and($summary['limits']['categories_used'])->toBe(2)
        ->and($summary['limits']['categories_remaining'])->toBe(3);
});
