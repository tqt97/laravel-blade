<?php

namespace App\Console\Commands;

use App\Booking\Actions\ExpireBooking;
use App\Booking\Enums\BookingStatus;
use App\Booking\Models\Booking;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'booking:expire-holds {--chunk=100 : Number of holds to process per batch}';

    protected $description = 'Expire booking holds whose expiration time has passed';

    public function handle(ExpireBooking $expireBooking): int
    {
        $count = 0;

        Booking::query()
            ->where('status', BookingStatus::Held->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($bookings) use ($expireBooking, &$count): void {
                foreach ($bookings as $booking) {
                    $count += (int) $expireBooking->execute($booking);
                }
            });

        $this->info("Expired {$count} booking hold(s).");

        return self::SUCCESS;
    }
}
