<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

use App\Models\User;

/** Checks a Google Play Integrity token for a collector phone (spec §18.3). */
interface IntegrityVerifier
{
    public function verify(?string $integrityToken, User $user): IntegrityVerdict;
}
