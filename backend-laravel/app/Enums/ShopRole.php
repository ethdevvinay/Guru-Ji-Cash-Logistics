<?php

declare(strict_types=1);

namespace App\Enums;

enum ShopRole: string
{
    case Owner = 'OWNER';
    case Staff = 'STAFF';
}
