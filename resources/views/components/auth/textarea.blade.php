@props(['label', 'name'])

@php
    $inputClasses = $errors->has($name)
        ? 'border-destructive focus:border-destructive focus:ring-destructive/15'
        : 'border-input focus:border-primary focus:ring-primary/15';
@endphp

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-foreground">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}"
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border bg-card px-3 py-2.5 text-sm text-card-foreground outline-none transition placeholder:text-muted-foreground focus:ring-4 '.$inputClasses]) }}>{{ $slot }}</textarea>
    @error($name)
        <p id="{{ $name }}-error" class="mt-2 break-words text-xs font-medium leading-5 text-destructive">{{ $message }}</p>
    @enderror
</div>
