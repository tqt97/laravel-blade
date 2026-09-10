<x-layouts.movie :title="__('booking.checkout.payment_action_title')">
    <div class="mx-auto max-w-6xl space-y-6 px-5 py-10 sm:px-8 lg:py-14" data-payment-status
        data-status-url="{{ route('user.bookings.payment-status', $booking) }}"
        data-success-url="{{ route('user.bookings.success', $booking) }}" data-client-secret="{{ $clientSecret ?? '' }}"
        data-return-url="{{ request()->url() }}" data-stripe-key="{{ config('services.stripe.key') ?? '' }}"
        data-copy-success-label="{{ __('booking.checkout.payment_test_card_copied') }}"
        data-loading-label="{{ __('booking.checkout.payment_loading') }}"
        data-processing-label="{{ __('booking.checkout.payment_processing') }}"
        data-delayed-label="{{ __('booking.checkout.payment_processing_delayed') }}"
        data-action-label="{{ __('booking.checkout.payment_action_button') }}"
        data-unavailable-label="{{ __('booking.checkout.payment_action_unavailable') }}"
        data-error-label="{{ __('booking.checkout.payment_action_failed') }}">

        <a href="{{ route('user.bookings.checkout', $booking) }}"
            class="text-sm font-semibold text-primary hover:underline">
            ← {{ __('booking.checkout.title') }}
        </a>
        <section class="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                        {{ __('booking.checkout.payment_order_summary') }}</p>
                    <h2 class="mt-1 truncate text-lg font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $booking->screening?->room?->name ?? '—' }} ·
                        {{ $booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }} ·
                        {{ __('booking.checkout.seats') }}: {{ $booking->items->map(fn ($item) => $item->screeningSeat?->seat?->row_label . $item->screeningSeat?->seat?->seat_number)->filter()->implode(', ') }}
                    </p>
                </div>
                <div class="shrink-0 sm:text-right">
                    <p class="text-xs text-muted-foreground">{{ __('booking.checkout.total') }}</p>
                    <p class="text-lg font-bold text-primary">
                        {{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, strtoupper((string) ($booking->pricing_currency ?? $booking->currency)))->format() }}
                    </p>
                </div>
            </div>
        </section>
        <section class="rounded-2xl border border-border bg-card p-4 text-center shadow-sm sm:p-5">
            <div class="mx-auto grid size-10 place-items-center rounded-full bg-warning-soft text-warning-foreground">!
            </div>
            <h1 class="mt-3 text-xl font-semibold tracking-tight sm:text-2xl">
                {{ $payment->status === \App\Enums\Payment\PaymentStatus::Unknown ? __('booking.checkout.payment_unknown_title') : ($payment->status === \App\Enums\Payment\PaymentStatus::RequiresAction ? __('booking.checkout.payment_action_title') : __('booking.checkout.payment_pending_title')) }}
            </h1>
            <p class="mx-auto mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                {{ $payment->status === \App\Enums\Payment\PaymentStatus::Unknown ? __('booking.checkout.payment_unknown_description') : ($payment->status === \App\Enums\Payment\PaymentStatus::RequiresAction ? __('booking.checkout.payment_action_description') : __('booking.checkout.payment_pending_description')) }}
            </p>
            <p class="mx-auto mt-4 max-w-3xl rounded-xl bg-muted/50 p-3 text-sm leading-6 text-muted-foreground">
                {{ __('booking.checkout.payment_test_mode_hint') }}
            </p>
        </section>
        @if ($clientSecret)
            <section class="mx-auto w-full rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold">{{ __('booking.checkout.payment_details_title') }}</h2>
                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div>
                        <div data-payment-loading class="mb-4 flex items-center gap-3 rounded-xl bg-muted/50 p-3 text-sm text-muted-foreground"
                            role="status" aria-live="polite">
                            <span class="size-4 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary"
                                aria-hidden="true"></span>
                            <span>{{ __('booking.checkout.payment_loading') }}</span>
                        </div>
                        <div data-payment-element class="rounded-xl border border-border bg-background p-4"></div>
                        <button type="button" data-stripe-confirm aria-busy="false"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90">
                            <span data-payment-button-spinner class="mr-2 hidden size-4 animate-spin rounded-full border-2 border-primary-foreground/30 border-t-primary-foreground"
                                aria-hidden="true"></span>
                            <span data-payment-button-label>{{ __('booking.checkout.payment_action_button') }}</span>
                        </button>
                        <div data-payment-processing class="mt-4 hidden flex items-center gap-3 rounded-xl bg-primary/5 p-3 text-sm text-primary"
                            role="status" aria-live="polite">
                            <span class="size-4 animate-spin rounded-full border-2 border-primary/30 border-t-primary"
                                aria-hidden="true"></span>
                            <span data-payment-processing-text>{{ __('booking.checkout.payment_processing') }}</span>
                        </div>
                        <p data-payment-processing-delayed class="mt-3 hidden rounded-xl bg-warning-soft p-3 text-sm leading-6 text-warning-foreground"
                            role="status" aria-live="polite">{{ __('booking.checkout.payment_processing_delayed') }}</p>
                        <p data-payment-error class="mt-4 hidden rounded-xl bg-destructive/10 p-4 text-sm text-destructive"
                            role="alert"></p>
                        <p class="mt-5 text-center text-xs text-muted-foreground">{{ __('booking.checkout.secure_payment') }}
                        </p>
                    </div>
                    @if (app()->environment('local'))
                        <aside class="rounded-xl border border-dashed border-primary/40 bg-primary/5 p-4"
                            data-test-cards>
                            <p class="text-sm font-semibold">{{ __('booking.checkout.payment_test_cards_title') }}</p>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                {{ __('booking.checkout.payment_test_cards_description') }}</p>
                            <div class="mt-3 grid gap-2">
                                @foreach ([['number' => '4242 4242 4242 4242', 'label' => __('booking.checkout.payment_test_card_success')], ['number' => '4000 0000 0000 3220', 'label' => __('booking.checkout.payment_test_card_3ds')], ['number' => '4000 0000 0000 9995', 'label' => __('booking.checkout.payment_test_card_insufficient_funds')], ['number' => '4000 0000 0000 0002', 'label' => __('booking.checkout.payment_test_card_declined')]] as $testCard)
                                    <button type="button" data-copy-test-card="{{ $testCard['number'] }}"
                                        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-left text-xs transition hover:border-primary hover:bg-primary/5">
                                        <span class="min-w-0">
                                            <span class="block font-semibold">{{ $testCard['label'] }}</span>
                                            <code
                                                class="mt-1 block truncate text-muted-foreground">{{ $testCard['number'] }}</code>
                                        </span>
                                        <span
                                            class="shrink-0 font-semibold text-primary">{{ __('booking.checkout.payment_test_card_copy') }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                {{ __('booking.checkout.payment_test_card_fields') }}</p>
                            <p class="mt-2 hidden text-xs font-semibold text-success" data-copy-test-card-status
                                role="status"></p>
                        </aside>
                    @endif
                </div>
            </section>
        @endif
    </div>
    @if ($clientSecret && config('services.stripe.key'))
        @push('scripts')
            <script src="https://js.stripe.com/v3/"></script>
        @endpush
    @endif
</x-layouts.movie>
