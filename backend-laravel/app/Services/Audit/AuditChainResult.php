<?php

declare(strict_types=1);

namespace App\Services\Audit;

final readonly class AuditChainResult
{
    public function __construct(
        public bool $intact,
        public int $checked,
        public ?int $brokenAtId = null,
        public ?string $reason = null,
    ) {}
}
