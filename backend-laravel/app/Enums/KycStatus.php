<?php

declare(strict_types=1);

namespace App\Enums;

enum KycStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
}
