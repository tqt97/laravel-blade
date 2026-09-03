@props(['user' => null, 'action', 'method' => 'POST'])

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

    <label
        class="flex items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-white/15 dark:bg-white/3"><input
            type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user?->is_admin ?? false))
            class="mt-0.5 size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500/20 dark:border-white/20 dark:bg-white/10">
        <span>
            <span class="block text-sm font-semibold text-neutral-900 dark:text-white">{{ __('ui.users.administrator_access') }}</span><span
                class="mt-1 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('ui.users.administrator_description') }}
            </span>
        </span>
    </label>
</x-admin.form-shell>
