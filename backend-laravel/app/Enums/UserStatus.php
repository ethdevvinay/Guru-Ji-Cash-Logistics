<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
    case Pending = 'PENDING';
}
