<?php

namespace App\Http\Requests\Admin;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', 'unique:bookable_resources,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                    $fail(__('booking.validation.timezone'));
                }
            }],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
