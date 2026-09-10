<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['establishment_id', 'weekday', 'is_closed', 'opens_at', 'closes_at'])]
class BusinessHour extends Model
{
    use HasFactory;

    public const WEEKDAY_LABELS = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAY_LABELS[$this->weekday] ?? '';
    }

    /**
     * Verifica se um horario "H:i:s" esta dentro da janela de funcionamento.
     * Suporta janelas que cruzam a meia-noite (ex.: 18:00 as 02:00).
     */
    public function coversTime(string $time): bool
    {
        if ($this->is_closed || ! $this->opens_at || ! $this->closes_at) {
            return false;
        }

        $opens = substr((string) $this->opens_at, 0, 8);
        $closes = substr((string) $this->closes_at, 0, 8);

        if ($opens <= $closes) {
            return $time >= $opens && $time <= $closes;
        }

        return $time >= $opens || $time <= $closes;
    }
}
