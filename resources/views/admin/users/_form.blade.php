@props(['user' => null, 'action', 'method' => 'POST'])

@php($isEditingOwnAdmin = $user?->is(auth()->user()) && $user->is_admin)

<x-admin.form-shell :title="$user ? __('ui.users.edit_title') : __('ui.users.create_title')"
    :description="$user ? __('ui.users.edit_description') : __('ui.users.create_description')"
    :action="$action" :method="$method" cancel-href="{{ route('admin.users.index') }}"
    :submit-label="$user ? __('ui.actions.save') : __('ui.users.create_title')">

    <div class="grid gap-5 sm:grid-cols-2">
        <x-auth.input :label="__('ui.auth_pages.full_name')" name="name" :value="old('name', $user?->name)" required />

        <x-auth.input :label="__('ui.auth_pages.email')" name="email" type="email" :value="old('email', $user?->email)" required />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-auth.password-input :label="$user ? __('ui.users.new_password_optional') : __('ui.auth_pages.password')" name="password"
            autocomplete="new-password" :required="!$user" />

        <x-auth.input :label="__('ui.auth_pages.confirm_password')" name="password_confirmation" type="password" autocomplete="new-password"
            :required="!$user" />
    </div>

    <label class="flex items-start gap-3 rounded-xl border border-border bg-muted p-4">
        @if ($isEditingOwnAdmin)
            <input type="hidden" name="is_admin" value="1">
        @endif
        <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user?->is_admin ?? false)) @disabled($isEditingOwnAdmin)
            class="mt-0.5 size-4 rounded border-input text-primary focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
        <span>
            <span class="block text-sm font-semibold text-card-foreground">{{ __('ui.users.administrator_access') }}</span><span
                class="mt-1 block text-xs leading-5 text-muted-foreground">{{ __('ui.users.administrator_description') }}
            </span>
            @if ($isEditingOwnAdmin)
                <span class="mt-2 block text-xs font-semibold text-warning-foreground">{{ __('ui.user_warnings.self_admin_warning') }}</span>
            @endif
        </span>
    </label>
</x-admin.form-shell>
