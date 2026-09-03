@props(['dark' => false])

<a href="{{ url('/') }}" class="inline-flex w-fit items-center gap-3 text-sm font-semibold tracking-wide transition hover:opacity-80 {{ $dark ? 'text-white' : 'text-[#ff2d20] dark:text-[#ff6b61]' }}">
    <x-ui.laravel-mark class="h-6 w-auto" />
    <span data-sidebar-label class="text-neutral-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
</a>
