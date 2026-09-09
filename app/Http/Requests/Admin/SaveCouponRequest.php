<?php

namespace App\Http\Requests\Admin;

use App\Enums\Movie\Booking\CouponType;
use App\Support\Money\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveCouponRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'currency' => strtoupper((string) $this->input('currency')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(CouponType::cases(), 'value'))],
            'value' => ['required', 'integer', 'min:1'],
            'maximum_discount_minor_units' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3', 'in:'.implode(',', Currency::codes())],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
