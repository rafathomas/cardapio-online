<?php

declare(strict_types=1);

use App\Models\BusinessHour;
use App\Models\Establishment;
use Carbon\CarbonImmutable;

it('reconhece horario dentro da janela normal', function (): void {
    $hour = new BusinessHour(['is_closed' => false, 'opens_at' => '18:00:00', 'closes_at' => '23:00:00']);

    expect($hour->coversTime('18:00:00'))->toBeTrue()
        ->and($hour->coversTime('20:30:00'))->toBeTrue()
        ->and($hour->coversTime('23:00:00'))->toBeTrue()
        ->and($hour->coversTime('17:59:00'))->toBeFalse()
        ->and($hour->coversTime('23:01:00'))->toBeFalse();
});

it('suporta janela que cruza a meia-noite', function (): void {
    $hour = new BusinessHour(['is_closed' => false, 'opens_at' => '18:00:00', 'closes_at' => '02:00:00']);

    expect($hour->coversTime('19:00:00'))->toBeTrue()
        ->and($hour->coversTime('23:59:00'))->toBeTrue()
        ->and($hour->coversTime('01:00:00'))->toBeTrue()
        ->and($hour->coversTime('03:00:00'))->toBeFalse()
        ->and($hour->coversTime('12:00:00'))->toBeFalse();
});

it('esta sempre fechado quando o dia esta marcado como fechado', function (): void {
    $hour = new BusinessHour(['is_closed' => true, 'opens_at' => '18:00:00', 'closes_at' => '23:00:00']);

    expect($hour->coversTime('20:00:00'))->toBeFalse();
});

it('usa o horario cadastrado para decidir aberto ou fechado', function (): void {
    $establishment = makeEstablishment();

    // Quarta-feira, 20h.
    $now = CarbonImmutable::parse('2026-01-07 20:00:00', 'America/Sao_Paulo');

    $establishment->businessHours()->create([
        'weekday' => (int) $now->dayOfWeek,
        'is_closed' => false,
        'opens_at' => '18:00:00',
        'closes_at' => '23:00:00',
    ]);

    expect($establishment->refresh()->isOpen($now))->toBeTrue()
        ->and($establishment->isOpen($now->setTime(15, 0)))->toBeFalse();
});

it('fecha quando nao ha horario cadastrado para o dia', function (): void {
    $establishment = makeEstablishment();

    expect($establishment->isOpen(CarbonImmutable::parse('2026-01-07 20:00:00')))->toBeFalse();
});

it('deixa o override manual vencer o horario', function (): void {
    $establishment = makeEstablishment();
    $now = CarbonImmutable::parse('2026-01-07 20:00:00', 'America/Sao_Paulo');

    $establishment->businessHours()->create([
        'weekday' => (int) $now->dayOfWeek,
        'is_closed' => false,
        'opens_at' => '18:00:00',
        'closes_at' => '23:00:00',
    ]);
    $establishment->refresh();

    $establishment->manual_status = Establishment::STATUS_CLOSED;
    expect($establishment->isOpen($now))->toBeFalse();

    $establishment->manual_status = Establishment::STATUS_OPEN;
    expect($establishment->isOpen($now->setTime(4, 0)))->toBeTrue();
});
