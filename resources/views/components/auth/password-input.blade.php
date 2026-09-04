@props(['label', 'name', 'generate' => false, 'hint' => null, 'actionText' => null, 'actionHref' => null])

@php
    $inputClasses = $errors->has($name)
        ? 'border-destructive focus:border-destructive focus:ring-destructive/15'
        : 'border-input focus:border-primary focus:ring-primary/15';
@endphp

<div>
    <div class="mb-2 flex items-center justify-between gap-3">
        <label for="{{ $name }}" class="block text-sm font-medium text-foreground">{{ $label }}</label>
        @if ($actionText && $actionHref)
            <a href="{{ $actionHref }}"
                class="text-xs font-semibold text-primary transition hover:text-primary-strong">{{ $actionText }}</a>
        @elseif ($generate)
            <button type="button" data-password-generate="{{ $name }}"
                class="text-xs font-semibold text-primary transition hover:text-primary-strong">{{ __('ui.password.generate') }}</button>
        @endif
    </div>
    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}" type="password" {{ $attributes->merge(['class' => 'block w-full rounded-lg border bg-card px-3 py-2.5 pr-12 text-sm text-card-foreground outline-none transition placeholder:text-muted-foreground focus:ring-4 ' . $inputClasses]) }}>
        <button type="button" data-password-toggle="{{ $name }}" data-password-show="{{ __('ui.password.show') }}"
            data-password-hide="{{ __('ui.password.hide') }}" aria-pressed="false" title="{{ __('ui.password.show') }}"
            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted-foreground transition hover:text-foreground">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="2.5" />
            </svg>
            <span class="sr-only">{{ __('ui.password.show') }}</span>
        </button>
    </div>
    @if ($hint)
    <p class="mt-2 text-xs text-muted-foreground">{{ $hint }}</p>@endif
    @error($name)
    <p class="mt-2 break-words text-xs font-medium leading-5 text-destructive">{{ $message }}</p>@enderror
</div>
