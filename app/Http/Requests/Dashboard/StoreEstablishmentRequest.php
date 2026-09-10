<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\EstablishmentSegment;
use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Establishment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'segment' => ['required', Rule::enum(EstablishmentSegment::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['required', 'string', 'min:10', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:60'],
            'address_street' => ['nullable', 'string', 'max:150'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:100'],
            'address_district' => ['nullable', 'string', 'max:100'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_state' => ['nullable', 'string', 'size:2'],
            'address_zipcode' => ['nullable', 'string', 'max:9'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O link deve conter apenas letras minúsculas, números e hífens.',
            'primary_color.regex' => 'A cor principal deve estar no formato #RRGGBB.',
            'secondary_color.regex' => 'A cor secundária deve estar no formato #RRGGBB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'segment' => 'segmento',
            'whatsapp' => 'WhatsApp',
            'slug' => 'link',
        ];
    }
}
