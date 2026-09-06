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
        $messages = OutboxMessage::query()->whereNull('published_at')->whereNull('failed_at')
            ->where('available_at', '<=', now())->oldest()->limit((int) $this->option('limit'))->get();
        foreach ($messages as $message) {
            PublishOutboxMessage::dispatch($message->id);
        }
        $this->info("Dispatched {$messages->count()} outbox message(s).");

        return self::SUCCESS;
    }
}
