<?php

namespace App\Queries\Cinema;

use App\Models\Cinema\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BookingReport
{
    /** @return array{orders:int,tickets:int,checked_in:int,revenue_minor_units:int,refunded_minor_units:int} */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = DB::table('bookings')->whereNotNull('screening_id')->whereBetween('created_at', [$from, $to]);
        $tickets = DB::table('booking_items')->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereBetween('bookings.created_at', [$from, $to]);

        return [
            'orders' => (clone $base)->count(),
            'tickets' => (clone $tickets)->whereIn('booking_items.status', ['issued', 'checked_in'])->count(),
            'checked_in' => (clone $tickets)->where('booking_items.status', 'checked_in')->count(),
            'revenue_minor_units' => (int) (clone $base)->whereIn('status', ['confirmed', 'completed'])->sum('total_minor_units'),
            'refunded_minor_units' => (int) DB::table('payments')
                ->join('bookings', function ($join): void {
                    $join->on('bookings.id', '=', 'payments.payable_id')
                        ->where('payments.payable_type', '=', Booking::class);
                })
                ->where('payments.status', 'refunded')
                ->whereBetween('bookings.created_at', [$from, $to])
                ->sum('payments.amount_minor_units'),
        ];
    }
}
