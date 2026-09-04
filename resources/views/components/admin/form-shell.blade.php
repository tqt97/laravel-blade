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

<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card']) }}>
    <div class="border-b border-border p-5">
        <h3 class="font-semibold text-card-foreground">{{ $title }}</h3>
        @if ($description)
        <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>@endif
    </div>
    <form method="POST" @if ($action) action="{{ $action }}" @endif class="space-y-6 p-5">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-5">
            <x-admin.button type="reset" variant="secondary" icon="close">
                {{ $resetLabel }}
            </x-admin.button>
            @if ($cancelHref)<x-admin.button variant="secondary" icon="close"
                :href="$cancelHref">{{ __('ui.actions.cancel') }}</x-admin.button>
            @endif

            <x-admin.button type="submit" :icon="$submitIcon">{{ $submitLabel }}</x-admin.button>
        </div>
    </form>
</section>
