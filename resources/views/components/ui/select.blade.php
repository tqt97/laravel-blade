<select {{ $attributes->merge(['class' => 'ui-action w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-card-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 disabled:opacity-60 aria-invalid:border-destructive']) }}>
    {{ $slot }}
</select>
