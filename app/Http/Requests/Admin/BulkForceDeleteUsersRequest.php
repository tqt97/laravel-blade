<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkForceDeleteUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        // Force delete is irreversible, so only already-soft-deleted users
        // may reach the Action layer.
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn (Builder $query): Builder => $query->whereNotNull('deleted_at')),
            ],
        ];
    }

    public function messages(): array
    {
        return ['ids.*.exists' => __('admin.users.errors.invalid_bulk_state')];
    }
}
