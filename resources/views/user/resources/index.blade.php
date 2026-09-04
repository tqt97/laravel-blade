<x-layouts.user :title="__('booking.resources.title')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.resources.title') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.resources.description') }}</p>
        </div>
        @if ($resources->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                {{ __('booking.resources.empty') }}</div>
        @else
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($resources as $resource)
                    <article class="flex flex-col rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="text-lg font-semibold">{{ $resource->name }}</h2><span
                                    class="rounded-full bg-success-soft px-2.5 py-1 text-xs font-semibold text-success-foreground">{{ __('booking.resources.active') }}</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ $resource->description }}</p>
                            <p class="mt-4 text-xs text-muted-foreground">
                                {{ __('booking.resources.timezone', ['timezone' => $resource->timezone]) }}</p>
                        </div>
                        <x-admin.button :href="route('user.bookings.create', ['resource_id' => $resource->id])" icon="plus"
                            class="mt-6 w-full">{{ __('booking.resources.book') }}</x-admin.button>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.user>
