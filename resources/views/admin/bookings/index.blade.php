<x-layouts.auth :title="__('booking.admin.bookings_title')" :heading="__('booking.admin.bookings_title')">
    <x-slot:breadcrumbs><x-admin.breadcrumbs :items="[['label' => __('booking.admin.bookings_title')]]" /></x-slot:breadcrumbs>
    <div class="space-y-6">
        <x-admin.page-header :title="__('booking.admin.bookings_title')" :description="__('booking.admin.bookings_description')" />
        <form method="POST" action="{{ route('admin.tickets.check-in') }}" class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 sm:flex-row sm:items-end">
            @csrf
            <div class="min-w-0 flex-1"><x-auth.input :label="__('cinema.check_in.ticket_code')" name="ticket_code" maxlength="64" required /></div>
            <x-admin.button type="submit" icon="save">{{ __('cinema.check_in.submit') }}</x-admin.button>
        </form>
        @if (session('status'))
            <x-admin.toast :message="__(session('status'))" />
        @endif
        <x-admin.table-shell :title="__('booking.admin.bookings_title')">
            <table class="min-w-full divide-y divide-border text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('booking.bookings.resource') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('booking.bookings.user') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('booking.bookings.period') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('booking.bookings.status') }}</th>
                        <th class="whitespace-nowrap px-5 py-3 text-right font-semibold">{{ __('booking.bookings.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">@forelse ($bookings as $booking)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}<span class="block text-xs font-normal text-muted-foreground">{{ $booking->screening?->room?->name }}</span></td>
                        <td class="px-5 py-4">{{ $booking->user?->name ?? '—' }}<span
                                class="block text-xs text-muted-foreground">{{ $booking->user?->email }}</span></td>
                        <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                            {{ $booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }} –
                            {{ $booking->screening?->ends_at?->timezone(config('app.timezone'))->format('H:i') ?? '—' }}</td>
                        <td class="px-5 py-4"><span
                                class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ __('booking.status.' . $booking->status->value) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right">
                            @if (in_array($booking->status, [\App\Enums\Movie\Booking\BookingStatus::Held, \App\Enums\Movie\Booking\BookingStatus::PendingPayment], true))
                                <x-admin.button type="button" variant="danger" icon="close" icon-only
                                    :title="__('booking.bookings.cancel')"
                                    aria-label="{{ __('booking.bookings.cancel') }}: {{ $booking->screening?->movie?->title ?? '—' }}"
                                    data-modal-open="cancel-booking-modal" data-modal-action="{{ route('admin.bookings.cancel', $booking) }}"
                                    data-modal-method="PATCH" />
                            @endif
                            @if (in_array($booking->payment?->status, [\App\Enums\Payment\PaymentStatus::Succeeded, \App\Enums\Payment\PaymentStatus::RequiresRefund], true))
                                <form method="POST" action="{{ route('admin.bookings.refund', $booking) }}" class="inline" onsubmit="return confirm('{{ __('booking.admin.refund_confirm') }}')">@csrf<x-admin.button type="submit" variant="ghost" icon="restore" icon-only :title="__('booking.admin.refund')" aria-label="{{ __('booking.admin.refund') }}" /></form>
                            @endif
                            @if (! in_array($booking->status, [\App\Enums\Movie\Booking\BookingStatus::Held, \App\Enums\Movie\Booking\BookingStatus::PendingPayment, \App\Enums\Movie\Booking\BookingStatus::Confirmed], true) && $booking->payment?->status !== \App\Enums\Payment\PaymentStatus::Succeeded)<span class="text-xs text-muted-foreground">—</span>@endif
                        </td>
                </tr>@empty<tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-muted-foreground">
                            {{ __('booking.admin.bookings_empty') }}</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </x-admin.table-shell>
        <div>{{ $bookings->links() }}</div>
    </div>
    <x-admin.confirm-modal id="cancel-booking-modal" :title="__('booking.bookings.cancel')"
        :description="__('booking.admin.cancel_confirm')" :confirm-label="__('booking.bookings.cancel')" method="PATCH" />
</x-layouts.auth>
