<?php

namespace App\Http\Requests\Admin;

use App\Models\Cinema\Concession;
use App\Support\Money\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConcessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $concession = $this->route('concession');

        return [
            'name' => ['required', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('concessions', 'sku')->ignore($concession instanceof Concession ? $concession->getKey() : null)],
            'price_minor_units' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', Currency::codes())],
            'stock' => ['nullable', 'integer', 'min:0'],
            'stock_reason' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
