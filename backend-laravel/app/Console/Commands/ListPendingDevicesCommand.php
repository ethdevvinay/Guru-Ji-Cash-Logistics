<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Command;

final class ListPendingDevicesCommand extends Command
{
    protected $signature = 'devices:pending';

    protected $description = 'List collector phones waiting for approval';

    public function handle(): int
    {
        $rows = Device::query()
            ->with('user')
            ->where('status', DeviceStatus::PendingApproval->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Device $device): array => [
                $device->public_id,
                $device->user->name,
                $device->user->mobile,
                trim(($device->manufacturer ?? '').' '.($device->model ?? '')),
                $device->created_at?->copy()->setTimezone('Asia/Kolkata')->format('d M Y h:i A'),
            ]);

        $this->table(['Device', 'Collector', 'Mobile', 'Phone', 'Registered (IST)'], $rows->all());

        return self::SUCCESS;
    }
}
