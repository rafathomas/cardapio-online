<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddonGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('establishment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'min_options' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'max_options' => ['sometimes', 'integer', 'min:1', 'max:20', 'gte:min_options'],
            'is_active' => ['sometimes', 'boolean'],
            'addons' => ['sometimes', 'array', 'max:50'],
            'addons.*.name' => ['required', 'string', 'max:80'],
            'addons.*.price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'addons.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
