<?php

declare(strict_types=1);

namespace App\Enums;

/** The only three roles the platform will ever have (spec §0.1). */
enum UserRole: string
{
    case Admin = 'admin';
    case Collector = 'collector';
    case Retailer = 'retailer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
