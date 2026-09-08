<x-layouts.storefront :title="__('booking.checkout.payment_action_title')">
    <div class="mx-auto max-w-lg space-y-6 px-5 py-12 sm:px-8" data-payment-status
        data-status-url="{{ route('user.bookings.payment-status', $booking) }}"
        data-success-url="{{ route('user.bookings.success', $booking) }}"
        data-client-secret="{{ $clientSecret ?? '' }}"
        data-stripe-key="{{ config('services.stripe.key') ?? '' }}"
        data-action-label="{{ __('booking.checkout.payment_action_button') }}"
        data-unavailable-label="{{ __('booking.checkout.payment_action_unavailable') }}"
        data-error-label="{{ __('booking.checkout.payment_action_failed') }}">
        <a href="{{ route('user.bookings.checkout', $booking) }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('booking.checkout.title') }}</a>
        <section class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-warning-soft text-warning-foreground">!</div>
            <h1 class="mt-5 text-center text-2xl font-semibold tracking-tight">
                {{ $payment->getRawOriginal('status') === 'requires_action' ? __('booking.checkout.payment_action_title') : __('booking.checkout.payment_pending_title') }}
            </h1>
            <p class="mt-3 text-center text-sm leading-6 text-muted-foreground">
                {{ $payment->getRawOriginal('status') === 'requires_action' ? __('booking.checkout.payment_action_description') : __('booking.checkout.payment_pending_description') }}
            </p>
            @if ($clientSecret)
                <button type="button" data-stripe-confirm aria-busy="false" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90">
                    {{ __('booking.checkout.payment_action_button') }}
                </button>
            @endif
            <p data-payment-error class="mt-4 hidden rounded-xl bg-destructive/10 p-4 text-sm text-destructive" role="alert"></p>
            <p class="mt-6 text-center text-xs text-muted-foreground">{{ __('booking.checkout.secure_payment') }}</p>
        </section>
    </div>
    @if ($clientSecret && config('services.stripe.key'))
        @push('scripts')
            <script src="https://js.stripe.com/v3/"></script>
        @endpush
    @endif
</x-layouts.storefront>
