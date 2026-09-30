<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditChainVerifier
{
    public function verify(): AuditChainResult
    {
        $previous = AuditHasher::GENESIS;
        $expected = 1;
        $checked = 0;
        $brokenAt = null;
        $reason = null;

        DB::table('audit_logs')->whereNotNull('seal_seq')->chunkById(1000, function (Collection $rows) use (&$previous, &$expected, &$checked, &$brokenAt, &$reason): bool {
            foreach ($rows as $row) {
                $id = (int) $row->id;

                if ((int) $row->seal_seq !== $expected) {
                    [$brokenAt, $reason] = [$id, "sequence gap: expected seal_seq {$expected}, found {$row->seal_seq}"];

                    return false;
                }

                if ($row->prev_hash !== $previous) {
                    [$brokenAt, $reason] = [$id, 'the link to the previous row does not match'];

                    return false;
                }

                if (! hash_equals((string) $row->hash, AuditHasher::hash($previous, AuditHasher::payload((array) $row)))) {
                    [$brokenAt, $reason] = [$id, 'the row content does not match its hash'];

                    return false;
                }

                $previous = (string) $row->hash;
                $expected++;
                $checked++;
            }

            return true;
        }, 'seal_seq');

        return new AuditChainResult($brokenAt === null, $checked, $brokenAt, $reason);
    }
}
