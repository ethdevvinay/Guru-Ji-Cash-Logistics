<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PersonalAccessToken;
use Laravel\Sanctum\Events\TokenAuthenticated;

final class RecordTokenIp
{
    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;
        $ip = request()->ip();

        if ($token instanceof PersonalAccessToken && $ip !== null && $token->last_used_ip !== $ip) {
            $token->forceFill(['last_used_ip' => $ip])->saveQuietly();
        }
    }
}
