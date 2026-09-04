@props(['dark' => false])

<a href="{{ url('/') }}"
    class="inline-flex w-fit items-center gap-3 text-sm font-semibold tracking-wide text-primary transition hover:opacity-80">
    <x-ui.laravel-mark class="h-6 w-auto" />
    <span data-sidebar-label class="text-foreground">{{ config('app.name', 'Laravel') }}</span>
</a>
