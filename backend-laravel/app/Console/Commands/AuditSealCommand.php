<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\AuditSealer;
use Illuminate\Console\Command;

final class AuditSealCommand extends Command
{
    protected $signature = 'audit:seal {--batch=500 : Rows per batch}';

    protected $description = 'Link new audit rows into the tamper-evident hash chain';

    public function handle(AuditSealer $sealer): int
    {
        $count = $sealer->seal((int) $this->option('batch'));
        $this->info("Sealed {$count} audit row(s).");

        return self::SUCCESS;
    }
}
