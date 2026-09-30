<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Console\Command;

final class RevokeDeviceCommand extends Command
{
    protected $signature = 'devices:revoke {device : Device public id} {--reason= : Why the phone is being revoked}';

    protected $description = 'Revoke a collector phone and end its sessions';

    public function handle(DeviceBindingService $binding): int
    {
        $device = Device::query()->where('public_id', (string) $this->argument('device'))->first();
        $reason = trim((string) $this->option('reason'));

        if ($device === null || $reason === '') {
            $this->error('Give an existing phone id and a --reason.');

            return self::FAILURE;
        }

        $binding->revoke($device, null, $reason);
        $this->info("Phone {$device->public_id} revoked.");

        return self::SUCCESS;
    }
}
