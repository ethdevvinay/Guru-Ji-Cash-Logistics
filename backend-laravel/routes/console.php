<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('audit:seal')->everyMinute()->withoutOverlapping();
Schedule::command('audit:verify-chain')->weeklyOn(0, '03:00')->timezone('Asia/Kolkata');
Schedule::command('security:prune-nonces')->hourly();
