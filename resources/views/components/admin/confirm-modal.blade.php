@props([
    'id',
    'title' => __('ui.modal.confirm_action'),
    'description' => __('ui.modal.confirm_description'),
    'confirmLabel' => __('ui.actions.confirm'),
    'confirmAction' => null,
    'method' => 'POST',
])

<div id="{{ $id }}" data-modal hidden
    class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true"
    aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description">
    <div data-modal-panel
        class="w-full max-w-md overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl shadow-black/30 dark:border-white/15 dark:bg-neutral-950">
        <div class="flex items-start gap-4 border-b border-neutral-200 p-6 dark:border-white/15">
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path d="M12 9v4M12 17h.01" />
                    <path d="M10.3 4.5 2.9 17a2 2 0 0 0 1.7 3h14.8a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z" />
                </svg>
            </div>
             <h2 data-modal-title id="{{ $id }}-title" class="min-w-0 flex-1 pt-1 text-lg font-semibold text-neutral-950 dark:text-white">
                {{ $title }}
            </h2>

            <x-admin.button type="button" variant="ghost" icon="close" icon-only :title="__('ui.actions.close')" data-modal-close />
        </div>
        <div class="p-6">
             <p data-modal-description id="{{ $id }}-description" class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                {{ $description }}
            </p>
        </div>
        <div
            class="flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50/70 p-6 dark:border-white/15 dark:bg-white/3">
            <x-admin.button type="button" variant="secondary" icon="close" data-modal-close>{{ __('ui.actions.cancel') }}
            </x-admin.button>
            <x-admin.button type="button" variant="danger" icon="trash" data-modal-confirm
                data-action="{{ $confirmAction }}" data-method="{{ $method }}"><span data-modal-label>{{ $confirmLabel }}</span>
            </x-admin.button>
        </div>
    </div>
</div>
