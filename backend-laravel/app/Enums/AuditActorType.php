<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditActorType: string
{
    case User = 'USER';
    case Anonymous = 'ANONYMOUS';
    case System = 'SYSTEM';
    case Scheduler = 'SCHEDULER';
    case Provider = 'PROVIDER';
}
