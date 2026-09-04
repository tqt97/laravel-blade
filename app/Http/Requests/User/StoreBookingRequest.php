<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', Rule::exists('bookable_resources', 'id')->where('is_active', true)],
            'start_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_at'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
