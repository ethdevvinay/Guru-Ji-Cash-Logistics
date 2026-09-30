<?php

declare(strict_types=1);

namespace App\Enums;

enum CollectorStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Inactive = 'INACTIVE';
}
