<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

use App\Models\User;

/**
 * Used until the Play Integrity verifier ships with the collector app (M2). It never claims
 * a pass, so `integrity_enforcement = ENFORCE` correctly refuses every phone until then.
 */
final class UncheckedIntegrityVerifier implements IntegrityVerifier
{
    public function verify(?string $integrityToken, User $user): IntegrityVerdict
    {
        return IntegrityVerdict::unchecked('Play Integrity verification is not configured yet.');
    }
}
