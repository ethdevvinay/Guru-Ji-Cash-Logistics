<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class DailyOutstandingReminderCommand extends Command
{
    protected $signature = 'reminders:daily-outstanding';
    protected $description = 'Dispatches daily 09:00 AM outstanding push notification reminders to retailers with a 24-hour anti-spam guard';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Starting Daily Outstanding Reminder run...');

        $sentCount = $notificationService->sendDailyOutstandingReminders();

        $this->info("Successfully dispatched {$sentCount} outstanding reminders with 24-hr anti-spam guard.");

        return Command::SUCCESS;
    }
}
