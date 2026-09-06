<?php

namespace App\Console\Commands;

use App\Actions\Booking\ExpireBooking;
use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'booking:expire-holds {--chunk=100 : Number of holds to process per batch}';

    protected $description = 'Expire booking holds whose expiration time has passed';

    public function handle(ExpireBooking $expireBooking): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            $this->error('The chunk size must be greater than zero.');

            return self::INVALID;
        }

        $count = 0;

        Booking::query()
            ->whereIn('status', [BookingStatus::Held->value, BookingStatus::PendingPayment->value])
            ->where('expires_at', '<=', now()->utc())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($bookings) use ($expireBooking, &$count): void {
                foreach ($bookings as $booking) {
                    $count += (int) $expireBooking->execute($booking);
                }
            });

        $this->info("Expired {$count} booking hold(s).");

        return self::SUCCESS;
    }
}
