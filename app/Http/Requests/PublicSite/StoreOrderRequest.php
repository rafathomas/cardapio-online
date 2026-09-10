<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido do consumidor final. Nao exige autenticacao.
 *
 * Repare no que NAO e aceito: preco, subtotal e total. Esses valores sao
 * sempre recalculados no servidor a partir dos ids enviados.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer.name' => ['required', 'string', 'min:2', 'max:120'],
            'customer.phone' => ['nullable', 'string', 'min:8', 'max:20'],
            'customer.delivery_type' => ['required', Rule::in(['pickup', 'delivery'])],
            'customer.address' => ['nullable', 'required_if:customer.delivery_type,delivery', 'string', 'max:255'],
            'customer.payment_method' => ['nullable', 'string', 'max:40'],
            'customer.notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.addon_ids' => ['sometimes', 'array', 'max:20'],
            'items.*.addon_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer.name' => 'nome',
            'customer.phone' => 'telefone',
            'customer.address' => 'endereço',
            'items' => 'itens do pedido',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Adicione ao menos um item ao carrinho.',
            'customer.address.required_if' => 'Informe o endereço para entrega.',
        ];
    }
}
