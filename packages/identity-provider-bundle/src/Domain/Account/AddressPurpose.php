<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Account;

/**
 * What an address of an account is for: signing in — the address of the account itself —, getting an account back,
 * or receiving its notices. An account has at most one address of each purpose; one without a recovery or a notice
 * address of its own uses its sign-in address.
 */
enum AddressPurpose: string
{
    case SignIn = 'sign_in';
    case Recovery = 'recovery';
    case Notices = 'notices';

    public const int MAX_LENGTH = 16;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $purpose): string => $purpose->value, self::cases());
    }
}
