<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Console\Command;

final class ApproveDeviceCommand extends Command
{
    protected $signature = 'devices:approve {device : Device public id}';

    protected $description = 'Approve a collector phone (for use until the admin panel ships)';

    public function handle(DeviceBindingService $binding): int
    {
        $device = Device::query()->where('public_id', (string) $this->argument('device'))->first();

        if ($device === null) {
            $this->error('No phone with that id.');

            return self::FAILURE;
        }

        $binding->approve($device, null);
        $this->info("Phone {$device->public_id} approved.");

        return self::SUCCESS;
    }
}
