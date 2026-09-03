@props(['label', 'name', 'type' => 'text', 'hint' => null])

@php
    $inputClasses = $errors->has($name)
        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/10'
        : 'border-neutral-300 focus:border-neutral-500 focus:ring-neutral-500/10 dark:border-white/20 dark:focus:border-neutral-300 dark:focus:ring-neutral-400/10';
@endphp

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg bg-white px-4 py-3 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:ring-4 dark:bg-white/[0.06] dark:text-white dark:placeholder:text-neutral-500 ' . $inputClasses]) }}
    >
    @if ($hint)
        <p class="mt-2 text-xs text-neutral-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-2 break-words text-xs font-medium leading-5 text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
