<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Establishment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('establishment')) ?? false;
    }

    /**
     * O formulario e brasileiro: aceita "29,90" e "1.234,56" alem do
     * formato com ponto. A normalizacao acontece antes das regras.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['price', 'promo_price'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value) && str_contains($value, ',')) {
                $normalized[$field] = str_replace(['.', ','], ['', '.'], trim($value));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        /** @var Establishment $establishment */
        $establishment = $this->route('establishment');

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Precos chegam em reais e sao convertidos para centavos no controller.
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'promo_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('establishment_id', $establishment->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cardapio.uploads.max_image_kb')],
            'addon_group_ids' => ['sometimes', 'array'],
            'addon_group_ids.*' => [
                Rule::exists('addon_groups', 'id')->where('establishment_id', $establishment->id),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $price = $this->input('price');
                $promo = $this->input('promo_price');

                if ($promo !== null && $promo !== '' && $price !== null
                    && (float) $promo >= (float) $price) {
                    $validator->errors()->add(
                        'promo_price',
                        'O preço promocional deve ser menor que o preço normal.',
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'price' => 'preço',
            'promo_price' => 'preço promocional',
            'category_id' => 'categoria',
        ];
    }
}
