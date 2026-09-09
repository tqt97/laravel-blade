<?php

namespace App\Console\Commands;

use App\Jobs\PublishOutboxMessage;
use App\Models\Infrastructure\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:outbox-publish {--limit=100}')]
#[Description('Dispatch pending transactional outbox messages.')]
class OutboxPublish extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $claimBefore = now()->subHour();

        $messages = OutboxMessage::query()
            ->whereNull('published_at')
            ->whereNull('failed_at')
            ->where('available_at', '<=', now())
            ->where(function ($query) use ($claimBefore): void {
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $claimBefore);
            })
            ->oldest()
            ->limit((int) $this->option('limit'))
            ->get();

        $claimedCount = 0;

        foreach ($messages as $message) {
            $claimed = OutboxMessage::query()
                ->whereKey($message->id)
                ->whereNull('published_at')
                ->whereNull('failed_at')
                ->where(function ($query) use ($claimBefore): void {
                    $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $claimBefore);
                })
                ->update(['claimed_at' => now()->utc()]);

            if ($claimed === 1) {
                PublishOutboxMessage::dispatch($message->id);
                $claimedCount++;
            }
        }

        $this->info("Dispatched {$claimedCount} outbox message(s).");

        return self::SUCCESS;
    }
}
