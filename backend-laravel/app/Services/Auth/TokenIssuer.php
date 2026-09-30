<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

final class TokenIssuer
{
    public const REGISTRATION_TOKEN_NAME = 'device-registration';

    public function __construct(private readonly Settings $settings) {}

    public function issueRetailerToken(User $user): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addDays((int) $this->settings->get('retailer_token_days'));

        return IssuedToken::from($user->createToken('retailer-app', [TokenAbility::Retailer->value], $expiresAt), $user);
    }

    public function issueCollectorToken(User $user, Device $device): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addHours((int) $this->settings->get('collector_token_hours'));
        $new = $user->createToken('collector-app', [TokenAbility::Collector->value], $expiresAt);
        $new->accessToken->forceFill(['device_id' => $device->id])->save();

        return IssuedToken::from($new, $user);
    }

    /** A ten-minute token that can only register a phone and poll its approval. One is live at a time. */
    public function issueRegistrationToken(User $user): IssuedToken
    {
        $user->tokens()->where('name', self::REGISTRATION_TOKEN_NAME)->delete();

        $new = $user->createToken(self::REGISTRATION_TOKEN_NAME, [TokenAbility::DeviceRegister->value], CarbonImmutable::now()->addMinutes(10));

        return IssuedToken::from($new, $user);
    }

    /** Replaces the current token with a fresh one carrying the same binding. */
    public function reissue(User $user, PersonalAccessToken $current): IssuedToken
    {
        $issued = match ($user->role) {
            UserRole::Collector => $this->issueCollectorToken(
                $user,
                $current->device ?? throw new ApiException('DEVICE_NOT_BOUND', 'This phone is not registered for your account.', 403),
            ),
            UserRole::Retailer => $this->issueRetailerToken($user),
            UserRole::Admin => throw new ApiException('FORBIDDEN', 'Admin sessions do not use app tokens.', 403),
        };

        $current->delete();

        return $issued;
    }
}
