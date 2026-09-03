<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkRestoreUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        // Restore is intentionally restricted to trashed rows. This keeps
        // the endpoint's contract explicit and makes stale UI selections
        // return a translated validation error.
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
