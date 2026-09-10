<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessHour */
class BusinessHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'weekday' => $this->weekday,
            'weekday_label' => $this->weekdayLabel(),
            'is_closed' => $this->is_closed,
            'opens_at' => $this->opens_at ? substr((string) $this->opens_at, 0, 5) : null,
            'closes_at' => $this->closes_at ? substr((string) $this->closes_at, 0, 5) : null,
        ];
    }
}
