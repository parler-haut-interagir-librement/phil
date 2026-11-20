<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Account;

/**
 * The state of an account, generic enough for any application: only an active account signs in.
 *
 * - `active`: the account signs in;
 * - `pending`: the account waits for a decision of the application, such as an approval, and does not sign in yet;
 * - `suspended`: the account is set aside for a while, by a moderator or after a doubt, and may come back;
 * - `closed`: the account is closed, by its owner or by the application, and waits for its removal.
 */
enum AccountState: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public const int MAX_LENGTH = 16;

    public function signsIn(): bool
    {
        return self::Active === $this;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }
}
