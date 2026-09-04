@props(['user'])

@php($isCurrentUser = auth()->user()?->is($user) ?? false)

<div class="flex justify-end gap-1">
    @if ($user->trashed())
        <x-admin.button variant="secondary" type="button" icon="restore" icon-only :title="__('ui.users.restore')"
            aria-label="{{ __('ui.users.restore_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal"
            data-modal-action="{{ route('admin.users.restore', $user->id) }}" data-modal-method="PATCH"
            data-modal-title="{{ __('ui.users.restore_title') }}" data-modal-description="{{ __('ui.users.restore_description') }}"
            data-modal-confirm-label="{{ __('ui.users.restore_confirm') }}" />
        @unless ($isCurrentUser)
            <x-admin.button variant="danger" type="button" icon="trash" icon-only :title="__('ui.users.force_delete')"
            aria-label="{{ __('ui.users.force_delete_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal"
            data-modal-action="{{ route('admin.users.force-delete', $user->id) }}" data-modal-method="DELETE"
            data-modal-title="{{ __('ui.users.force_delete_title') }}" data-modal-description="{{ __('ui.users.force_delete_description') }}"
                data-modal-confirm-label="{{ __('ui.users.force_delete_confirm') }}" />
        @endunless
    @else
        <x-admin.button variant="ghost" href="{{ route('admin.users.edit', $user) }}" icon="pencil" icon-only
            :title="__('ui.users.edit')" aria-label="{{ __('ui.users.edit_user', ['name' => $user->name]) }}" />
        @unless ($isCurrentUser)
            <x-admin.button variant="danger" type="button" icon="trash" icon-only :title="__('ui.users.delete')"
            aria-label="{{ __('ui.users.delete_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal"
            data-modal-action="{{ route('admin.users.destroy', $user) }}" data-modal-method="DELETE"
            data-modal-title="{{ __('ui.users.delete_title') }}" data-modal-description="{{ __('ui.users.delete_description') }}"
                data-modal-confirm-label="{{ __('ui.users.delete_confirm') }}" />
        @endunless
    @endif
</div>
