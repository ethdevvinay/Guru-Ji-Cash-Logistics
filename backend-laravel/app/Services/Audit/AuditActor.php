<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditActorType;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

final readonly class AuditActor
{
    private function __construct(
        public AuditActorType $type,
        public ?int $userId,
        public ?string $role,
    ) {}

    public static function user(User $user): self
    {
        return new self(AuditActorType::User, $user->id, $user->role->value);
    }

    public static function system(): self
    {
        return new self(AuditActorType::System, null, null);
    }

    public static function scheduler(): self
    {
        return new self(AuditActorType::Scheduler, null, null);
    }

    public static function anonymous(): self
    {
        return new self(AuditActorType::Anonymous, null, null);
    }

    /** The authenticated user; otherwise SYSTEM in console runs and ANONYMOUS for unauthenticated requests. */
    public static function current(): self
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return self::user($user);
        }

        return App::runningInConsole() && ! App::runningUnitTests() ? self::system() : self::anonymous();
    }

    public function isHttp(): bool
    {
        return $this->type === AuditActorType::User || $this->type === AuditActorType::Anonymous;
    }
}
