<x-layouts.user :title="__('booking.bookings.create')">
    <div class="mx-auto max-w-2xl space-y-8">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.bookings.create') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.resources.description') }}</p>
        </div>
        @if ($errors->any())
            <div role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive-soft p-4 text-sm text-destructive-foreground">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
        </div>@endif
        <form method="POST" action="{{ route('user.bookings.store') }}"
            class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">@csrf
            <div><label for="resource_id"
                    class="mb-2 block text-sm font-semibold">{{ __('booking.bookings.resource_label') }}</label><select
                    id="resource_id" name="resource_id" required
                    class="ui-input w-full">@foreach ($resources as $resource)
                    <option value="{{ $resource->id }}" @selected(old('resource_id', request('resource_id')) == $resource->id)>{{ $resource->name }}</option>@endforeach
                </select></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="start_at"
                        class="mb-2 block text-sm font-semibold">{{ __('booking.bookings.start') }}</label><input
                        id="start_at" name="start_at" type="datetime-local" value="{{ old('start_at') }}" required
                        class="ui-input w-full"></div>
                <div><label for="end_at"
                        class="mb-2 block text-sm font-semibold">{{ __('booking.bookings.end') }}</label><input
                        id="end_at" name="end_at" type="datetime-local" value="{{ old('end_at') }}" required
                        class="ui-input w-full"></div>
            </div>
            <div><label for="idempotency_key"
                    class="mb-2 block text-sm font-semibold">{{ __('booking.bookings.idempotency') }}</label><input
                    id="idempotency_key" name="idempotency_key" value="{{ old('idempotency_key') }}" maxlength="128"
                    class="ui-input w-full">
                <p class="mt-2 text-xs text-muted-foreground">{{ __('booking.bookings.idempotency_hint') }}</p>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-admin.button
                    :href="route('user.bookings.index')"
                    variant="secondary">{{ __('ui.actions.cancel') }}</x-admin.button><x-admin.button type="submit"
                    icon="plus">{{ __('booking.bookings.create') }}</x-admin.button></div>
        </form>
    </div>
</x-layouts.user>
