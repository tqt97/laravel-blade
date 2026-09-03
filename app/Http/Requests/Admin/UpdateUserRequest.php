<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_admin' => $this->boolean('is_admin')]);
    }

    /**
     * Protect the last administrator and prevent self-lockout.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->route('user');

            if (! $user instanceof User || $this->boolean('is_admin')) {
                return;
            }

            if ($user->is($this->user())) {
                $validator->errors()->add('is_admin', __('admin.users.errors.cannot_demote_self'));
            } elseif ($user->is_admin && User::query()->administrators()->count() <= 1) {
                $validator->errors()->add('is_admin', __('admin.users.errors.cannot_demote_last_admin'));
            }
        });
    }
}
