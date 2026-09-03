@props([
    'title',
    'description' => null,
    'action' => null,
    'method' => 'POST',
    'cancelHref' => null,
    'submitLabel' => __('ui.actions.save'),
    'submitIcon' => 'save',
    'resetLabel' => __('ui.actions.reset'),
])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-neutral-200/80 bg-white shadow-sm shadow-neutral-200/40 dark:border-white/10 dark:bg-white/[0.03] dark:shadow-none']) }}>
    <div class="border-b border-neutral-100 p-5 dark:border-white/10">
        <h3 class="font-semibold text-neutral-950 dark:text-white">{{ $title }}</h3>
        @if ($description)
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ $description }}</p>@endif
    </div>
    <form method="POST" @if ($action) action="{{ $action }}" @endif class="space-y-6 p-5">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
        <div
            class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-100 pt-5 dark:border-white/10">
            <x-admin.button type="reset" variant="secondary" icon="close">
                {{ $resetLabel }}
            </x-admin.button>
            @if ($cancelHref)<x-admin.button variant="secondary" icon="close" :href="$cancelHref">{{ __('ui.actions.cancel') }}</x-admin.button>
            @endif

            <x-admin.button type="submit" :icon="$submitIcon">{{ $submitLabel }}</x-admin.button>
        </div>
    </form>
</section>
