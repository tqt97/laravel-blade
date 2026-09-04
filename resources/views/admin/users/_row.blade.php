@props(['user'])

@php($isCurrentUser = auth()->user()?->is($user) ?? false)

<tr class="transition hover:bg-accent/50">
    <td class="px-5 py-4">
        <input type="checkbox" value="{{ $user->id }}" data-user-selection
            @disabled($isCurrentUser && ! $user->trashed())
            aria-label="{{ $isCurrentUser && ! $user->trashed() ? __('ui.user_warnings.self_delete_disabled') : __('ui.users.select_user', ['name' => $user->name]) }}"
            @if ($isCurrentUser && ! $user->trashed()) title="{{ __('ui.user_warnings.self_delete_disabled') }}" @endif
            class="size-4 cursor-pointer rounded border-input text-primary accent-primary disabled:cursor-not-allowed disabled:opacity-50">
    </td>
    <td class="whitespace-nowrap px-5 py-4">
        <p class="font-medium text-card-foreground">{{ $user->name }}</p>
        <p class="mt-1 text-xs text-muted-foreground">{{ $user->email }}</p>
    </td>
    <td class="px-5 py-4">
        @include('admin.users._status-badge', ['label' => $user->is_admin ? __('ui.users.admin') : __('ui.users.user'), 'variant' => $user->is_admin ? 'primary' : 'muted'])
    </td>
    <td class="px-5 py-4">
        @include('admin.users._status-badge', ['label' => $user->email_verified_at ? __('ui.users.verified') : __('ui.users.unverified'), 'variant' => $user->email_verified_at ? 'success' : 'warning'])
    </td>
    <td class="px-5 py-4">
        @include('admin.users._status-badge', ['label' => $user->two_factor_confirmed_at ? __('ui.users.enabled') : __('ui.users.disabled'), 'variant' => $user->two_factor_confirmed_at ? 'success' : 'muted'])
    </td>
    <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
        {{ $user->trashed() ? __('ui.users.deleted') : $user->created_at?->format('M d, Y') }}
    </td>
    <td class="px-5 py-4">
        @include('admin.users._actions', ['user' => $user])
    </td>
</tr>
