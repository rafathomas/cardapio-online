<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('establishment')) ?? false;
    }

    public function rules(): array
    {
        return [
            // Apenas o plano e o metodo vem do cliente. O valor vem do banco.
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'method' => ['sometimes', Rule::in(['checkout', 'pix'])],
        ];
    }
}
