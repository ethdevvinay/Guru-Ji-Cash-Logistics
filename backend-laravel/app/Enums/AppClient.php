<?php

declare(strict_types=1);

namespace App\Enums;

/** Which mobile app is logging in. */
enum AppClient: string
{
    case Retailer = 'retailer';
    case Collector = 'collector';
}
