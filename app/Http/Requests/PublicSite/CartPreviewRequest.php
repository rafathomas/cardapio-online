<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

/** Recalcula o carrinho no servidor para exibir totais confiaveis. */
class CartPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.addon_ids' => ['sometimes', 'array', 'max:20'],
            'items.*.addon_ids.*' => ['integer', 'min:1'],
        ];
    }
}
