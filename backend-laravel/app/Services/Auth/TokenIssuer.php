<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

final class TokenIssuer
{
    public function __construct(private readonly Settings $settings) {}

    public function issueRetailerToken(User $user): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addDays((int) $this->settings->get('retailer_token_days'));

        return IssuedToken::from($user->createToken('retailer-app', [TokenAbility::Retailer->value], $expiresAt), $user);
    }
}
