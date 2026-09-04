<?php

namespace App\Console\Commands;

use App\Models\UserManagementAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('audit:prune-user-management {--days= : Delete records older than this many days}')]
#[Description('Remove expired user-management audit records')]
class PruneUserManagementAudits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('audit.user_management_retention_days', 365));

        if ($days < 1) {
            throw new InvalidArgumentException('The audit retention period must be at least one day.');
        }

        $deleted = UserManagementAudit::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} user-management audit record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
