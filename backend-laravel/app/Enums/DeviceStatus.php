<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';
}
