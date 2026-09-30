<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenAbility: string
{
    case Retailer = 'retailer';
    case Collector = 'collector';
    case DeviceRegister = 'device:register';
}
