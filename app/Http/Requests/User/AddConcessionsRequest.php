<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddConcessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['quantities' => ['nullable', 'array'], 'quantities.*' => ['nullable', 'integer', 'min:0', 'max:'.config('booking.limits.max_combo_quantity')]];
    }
}
