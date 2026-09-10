<?php

use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('booking:expire-holds')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('app:outbox-publish')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('payments:recover-stuck')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('payments:alert-stuck')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('booking:send-reminders')->everyMinute()->withoutOverlapping()->onOneServer();

DevCommands::artisan('schedule:work', 'scheduler');
DevCommands::artisan('queue:work --tries=3 --timeout=90', 'queue');
