<?php

declare(strict_types=1);

namespace App\Enums;

enum TerritoryType: string
{
    case City = 'CITY';
    case Area = 'AREA';
    case Ward = 'WARD';
}
