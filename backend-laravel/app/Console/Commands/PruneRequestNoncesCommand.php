<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneRequestNoncesCommand extends Command
{
    protected $signature = 'security:prune-nonces';

    protected $description = 'Delete request nonces that can no longer be replayed';

    public function handle(): int
    {
        $deleted = DB::table('request_nonces')->where('expires_at', '<', now())->delete();
        $this->info("Deleted {$deleted} expired nonce(s).");

        return self::SUCCESS;
    }
}
