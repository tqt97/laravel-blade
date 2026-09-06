<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMovieRequest extends FormRequest
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
        return ['title' => ['required', 'string', 'max:255'], 'synopsis' => ['nullable', 'string', 'max:5000'], 'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'], 'rating' => ['nullable', 'string', 'max:16'], 'release_date' => ['nullable', 'date'], 'is_active' => ['sometimes', 'boolean']];
    }
}
