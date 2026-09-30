<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\AuditChainVerifier;
use Illuminate\Console\Command;

final class AuditVerifyChainCommand extends Command
{
    protected $signature = 'audit:verify-chain';

    protected $description = 'Recompute the audit hash chain and report the first break';

    public function handle(AuditChainVerifier $verifier): int
    {
        $result = $verifier->verify();

        if ($result->intact) {
            $this->info("Audit chain intact ({$result->checked} sealed rows checked).");

            return self::SUCCESS;
        }

        $this->error("Audit chain broken at audit_logs id={$result->brokenAtId}: {$result->reason}.");

        return self::FAILURE;
    }
}
