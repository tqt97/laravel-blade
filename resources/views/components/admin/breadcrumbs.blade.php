@props(['items' => []])

<nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
    <a href="{{ route('admin.dashboard') }}"
        class="ui-action rounded-md transition hover:text-neutral-950 dark:hover:text-white">
        {{ __('ui.app.home') }}
    </a>
    @foreach ($items as $item)
        <svg class="size-3.5 text-neutral-300 dark:text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" aria-hidden="true">
            <path d="m9 18 6-6-6-6" />
        </svg>
        @if (!$loop->last && isset($item['href']))
            <a href="{{ $item['href'] }}"
                class="ui-action rounded-md transition hover:text-neutral-950 dark:hover:text-white">{{ $item['label'] }}
            </a>
        @else
            <span aria-current="page" class="text-neutral-700 dark:text-neutral-200">
                {{ $item['label'] }}
            </span>
        @endif
    @endforeach
</nav>
