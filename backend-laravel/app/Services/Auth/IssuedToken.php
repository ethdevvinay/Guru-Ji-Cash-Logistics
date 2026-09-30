<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\NewAccessToken;
use LogicException;

final readonly class IssuedToken
{
    public function __construct(
        public string $plainText,
        public CarbonImmutable $expiresAt,
        public User $user,
        public PersonalAccessToken $token,
    ) {}

    public static function from(NewAccessToken $new, User $user): self
    {
        $token = $new->accessToken;

        if (! $token instanceof PersonalAccessToken || $token->expires_at === null) {
            throw new LogicException('Every issued token must use the app token model and expire.');
        }

        return new self($new->plainTextToken, CarbonImmutable::instance($token->expires_at), $user, $token);
    }
}
