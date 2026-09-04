<x-layouts.auth :title="__('booking.admin.edit_resource')">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.admin.edit_resource') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ $resource->name }}</p>
        </div>@if ($errors->any())
            <div role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive-soft p-4 text-sm text-destructive-foreground">
        {{ __('ui.validation.fix_errors') }}</div>@endif<form method="POST"
            action="{{ route('admin.resources.update', $resource) }}"
            class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm">@csrf @method('PUT')
            @include('admin.resources._form', ['submitLabel' => __('ui.actions.save')])</form>
    </div>
</x-layouts.auth>
