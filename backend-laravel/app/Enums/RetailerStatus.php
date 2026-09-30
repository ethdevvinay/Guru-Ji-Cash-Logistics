<?php

declare(strict_types=1);

namespace App\Enums;

enum RetailerStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
    case Inactive = 'INACTIVE';
}
