<x-layouts.user :title="$screening->movie->title">
    <div class="mx-auto max-w-5xl space-y-8" data-seat-picker>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('user.screenings.index') }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('cinema.screenings.title') }}</a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ $screening->movie->title }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ $screening->room->name }} · {{ $screening->starts_at->timezone($screening->room->timezone)->format('d/m/Y H:i') }}–{{ $screening->ends_at->timezone($screening->room->timezone)->format('H:i') }}</p>
            </div>
        </div>
        <x-auth.feedback />
        @error('seat_ids')<p class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('user.screenings.hold', $screening) }}" class="space-y-6">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
            <input type="hidden" name="seat_ids[]" value="" data-seat-input>
            <section class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8">
                <div class="mx-auto mb-8 max-w-md rounded-full bg-muted py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">{{ __('cinema.seats.screen') }}</div>
                <div class="mx-auto grid max-w-2xl gap-3">
                    @foreach ($screening->screeningSeats->groupBy(fn ($screeningSeat) => $screeningSeat->seat->row_label) as $row => $seats)
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center text-xs font-bold text-muted-foreground">{{ $row }}</span>
                            <div class="grid flex-1 gap-2" style="grid-template-columns: repeat({{ min(12, max(1, $seats->count())) }}, minmax(0, 1fr));">
                                @foreach ($seats as $screeningSeat)
                                    @php($available = $screeningSeat->status === \App\Enums\Cinema\ScreeningSeatStatus::Available)
                                    <button type="button" data-seat-id="{{ $screeningSeat->seat_id }}" @disabled(! $available)
                                        class="aspect-square rounded-lg border text-xs font-bold transition {{ $available ? 'border-border bg-background hover:border-primary hover:bg-primary/10' : 'cursor-not-allowed border-border bg-muted text-muted-foreground line-through' }}"
                                        title="{{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }} · {{ strtoupper($screeningSeat->seat->seat_type->value) }}">
                                        {{ $screeningSeat->seat->seat_number }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-8 flex flex-wrap justify-center gap-4 text-xs text-muted-foreground">
                    <span>● {{ __('cinema.seats.available') }}</span><span class="text-muted-foreground/50">● {{ __('cinema.seats.unavailable') }}</span><span class="text-primary">● {{ __('cinema.seats.selected') }}</span>
                </div>
            </section>
            <section class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:flex-row sm:p-6">
                <p class="text-sm text-muted-foreground"><span data-seat-count>0</span> {{ __('cinema.seats.selected_count') }}</p>
                <x-admin.button type="submit" icon="save" data-seat-submit disabled>{{ __('cinema.seats.hold') }}</x-admin.button>
            </section>
        </form>
    </div>
    <script>
        document.querySelectorAll('[data-seat-picker]').forEach((picker) => {
            const form = picker.querySelector('form'), buttons = picker.querySelectorAll('[data-seat-id]'), count = picker.querySelector('[data-seat-count]'), submit = picker.querySelector('[data-seat-submit]');
            const sync = () => { picker.querySelectorAll('[data-seat-input]').forEach((input) => input.remove()); const selected = [...buttons].filter((button) => button.dataset.selected === 'true'); selected.forEach((button) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'seat_ids[]'; input.value = button.dataset.seatId; form.append(input); }); count.textContent = selected.length; submit.disabled = selected.length === 0; };
            buttons.forEach((button) => button.addEventListener('click', () => { button.dataset.selected = button.dataset.selected !== 'true' ? 'true' : 'false'; button.classList.toggle('border-primary', button.dataset.selected === 'true'); button.classList.toggle('bg-primary/10', button.dataset.selected === 'true'); sync(); }));
        });
    </script>
</x-layouts.user>
