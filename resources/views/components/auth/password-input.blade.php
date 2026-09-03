@props(['label', 'name', 'generate' => false, 'hint' => null, 'actionText' => null, 'actionHref' => null])

@php
    $inputClasses = $errors->has($name)
        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/10'
        : 'border-neutral-300 focus:border-neutral-500 focus:ring-neutral-500/10 dark:border-white/20 dark:focus:border-neutral-300 dark:focus:ring-neutral-400/10';
@endphp

<div>
    <div class="mb-2 flex items-center justify-between gap-3">
        <label for="{{ $name }}" class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ $label }}</label>
        @if ($actionText && $actionHref)
            <a href="{{ $actionHref }}" class="text-xs font-semibold text-neutral-600 transition hover:text-neutral-700 dark:text-neutral-300 dark:hover:text-neutral-200">{{ $actionText }}</a>
        @elseif ($generate)
            <button type="button" data-password-generate="{{ $name }}" class="text-xs font-semibold text-neutral-600 transition hover:text-neutral-700 dark:text-neutral-300 dark:hover:text-neutral-200">Tạo mật khẩu</button>
        @endif
    </div>
    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}" type="password" {{ $attributes->merge(['class' => 'block w-full rounded-lg bg-white px-4 py-3 pr-12 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:ring-4 dark:bg-white/[0.06] dark:text-white dark:placeholder:text-neutral-500 ' . $inputClasses]) }}>
        <button type="button" data-password-toggle="{{ $name }}" aria-pressed="false" title="Hiện mật khẩu" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-neutral-400 transition hover:text-neutral-600 dark:text-neutral-500 dark:hover:text-neutral-300">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
            <span class="sr-only">Hiện mật khẩu</span>
        </button>
    </div>
    @if ($hint)<p class="mt-2 text-xs text-neutral-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-2 break-words text-xs font-medium leading-5 text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
</div>
