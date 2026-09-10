<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QrCode */
class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_url' => $this->target_url,
            'image_url' => $this->imageUrl(),
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
