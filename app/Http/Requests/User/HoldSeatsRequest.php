<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HoldSeatsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seat_ids' => ['required', 'array', 'min:1', 'max:'.config('booking.limits.max_seats')],
            'seat_ids.*' => ['required', 'integer', 'distinct'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:'.config('booking.limits.max_combo_quantity')],
        ];
    }
}
