<?php

namespace App\Http\Requests\Admin;

use App\Enums\Admin\UserRole;
use App\Enums\Admin\UserStatus;
use App\Enums\Admin\UserTwoFactorStatus;
use App\Enums\Admin\UserVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'verification' => ['nullable', Rule::enum(UserVerificationStatus::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'two_factor' => ['nullable', Rule::enum(UserTwoFactorStatus::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'per_page' => ['nullable', 'integer', 'in:15,30,50'],
        ];
    }
}
