<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScreeningRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:32', Rule::unique('screening_rooms', 'code')], 'timezone' => ['required', 'timezone'], 'rows' => ['required', 'string', 'regex:/^[A-Za-z](?:\s*,\s*[A-Za-z])*$/'], 'seats_per_row' => ['required', 'integer', 'min:1', 'max:50']];
    }
}
