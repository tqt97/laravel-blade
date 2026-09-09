<?php

namespace App\Console\Commands;

use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('booking:send-reminders {--chunk=100 : Number of bookings to claim per run}')]
#[Description('Queue reminders for confirmed bookings starting in about two hours')]
class SendBookingReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $now = CarbonImmutable::now('UTC');
        $from = $now->addHours(2)->subMinute();
        $until = $now->addHours(2)->addMinute();
        $ids = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereHas('screening', fn ($query) => $query->whereBetween('starts_at', [$from, $until]))
            ->orderBy('id')
            ->limit($chunk)
            ->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $claimed = DB::transaction(function () use ($id, $from, $until): bool {
                $booking = Booking::query()->whereKey($id)->lockForUpdate()->first();
                if ($booking === null || $booking->reminder_sent_at !== null || $booking->getRawOriginal('status') !== BookingStatus::Confirmed->value) {
                    return false;
                }
                $startsAt = $booking->screening()->value('starts_at');
                if ($startsAt === null || CarbonImmutable::parse((string) $startsAt, 'UTC')->isBefore($from) || CarbonImmutable::parse((string) $startsAt, 'UTC')->isAfter($until)) {
                    return false;
                }
                $booking->forceFill(['reminder_sent_at' => now()->utc()])->save();
                OutboxMessage::query()->create([
                    'aggregate_type' => Booking::class,
                    'aggregate_id' => $booking->getKey(),
                    'event_type' => OutboxEventType::BookingReminderDue,
                    'payload' => ['booking_id' => $booking->getKey()],
                ]);

                return true;
            }, 3);
            $count += (int) $claimed;
        }
        $this->info("Queued {$count} booking reminder(s).");

        return self::SUCCESS;
    }
}
