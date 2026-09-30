<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

final readonly class IntegrityVerdict
{
    /**
     * @param  'PASS'|'FAIL'|'UNCHECKED'  $status
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $status,
        public array $details = [],
    ) {}

    public static function unchecked(string $reason): self
    {
        return new self('UNCHECKED', ['reason' => $reason]);
    }

    public function passed(): bool
    {
        return $this->status === 'PASS';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'details' => $this->details, 'checked_at' => now()->toIso8601String()];
    }
}
