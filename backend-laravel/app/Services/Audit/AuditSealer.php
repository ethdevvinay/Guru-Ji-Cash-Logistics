<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Links committed, unsealed audit rows into the hash chain in the order it sees them.
 * Chain order is `seal_seq`, not `id`: a slow transaction may commit a lower id later.
 */
final class AuditSealer
{
    public function seal(int $batchSize = 500): int
    {
        return (int) Cache::lock('audit:seal', 120)->block(10, function () use ($batchSize): int {
            $last = DB::table('audit_logs')->whereNotNull('seal_seq')->orderByDesc('seal_seq')->first(['hash', 'seal_seq']);
            $previous = $last->hash ?? AuditHasher::GENESIS;
            $sequence = (int) ($last->seal_seq ?? 0);
            $sealed = 0;

            while (true) {
                $rows = DB::table('audit_logs')->whereNull('seal_seq')->orderBy('id')->limit($batchSize)->get();

                if ($rows->isEmpty()) {
                    return $sealed;
                }

                foreach ($rows as $row) {
                    $hash = AuditHasher::hash($previous, AuditHasher::payload((array) $row));

                    $updated = DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->whereNull('seal_seq')
                        ->update([
                            'seal_seq' => $sequence + 1,
                            'prev_hash' => $previous,
                            'hash' => $hash,
                            'sealed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                        ]);

                    if ($updated === 1) {
                        $sequence++;
                        $previous = $hash;
                        $sealed++;
                    }
                }
            }
        });
    }
}
