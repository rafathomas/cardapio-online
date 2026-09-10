<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Establishment;
use Illuminate\Validation\Rule;

class UpdateEstablishmentRequest extends StoreEstablishmentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('establishment')) ?? false;
    }

    public function rules(): array
    {
        /** @var Establishment $establishment */
        $establishment = $this->route('establishment');

        return [
            ...parent::rules(),
            'slug' => [
                'nullable', 'string', 'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('establishments', 'slug')->ignore($establishment->id),
                Rule::notIn(config('cardapio.reserved_slugs')),
            ],
            'manual_status' => ['nullable', Rule::in([Establishment::STATUS_OPEN, Establishment::STATUS_CLOSED])],
            'is_indexable' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cardapio.uploads.max_image_kb')],
            'cover' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cardapio.uploads.max_image_kb')],
        ];
    }
}
