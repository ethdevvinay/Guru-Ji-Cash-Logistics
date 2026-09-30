<?php

declare(strict_types=1);

namespace App\Enums;

enum LocationSource: string
{
    case Import = 'IMPORT';
    case Admin = 'ADMIN';
    case Onboarding = 'ONBOARDING';
}
