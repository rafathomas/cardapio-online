<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['establishment_id', 'token', 'target_url', 'path', 'version', 'scans'])]
class QrCode extends Model
{
    use HasFactory;

    protected $table = 'qrcodes';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'scans' => 'integer',
        ];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function imageUrl(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
