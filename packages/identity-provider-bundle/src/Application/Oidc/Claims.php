<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The claims of an account a client receives, by scope and nothing more (OpenID Connect Core, section 5.4): `sub`
 * always, `email` and `email_verified` for `email`, `name` for `profile`, `phone_number` and
 * `phone_number_verified` for `phone`.
 */
final class Claims
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $scopes
     *
     * @return array<string, bool|string>
     */
    public static function of(Account $account, array $scopes): array
    {
        $claims = ['sub' => $account->id()];

        if (\in_array('email', $scopes, true)) {
            $claims += ['email' => $account->email(), 'email_verified' => $account->isEmailVerified()];
        }

        if (\in_array('profile', $scopes, true) && null !== $account->displayName()) {
            $claims['name'] = $account->displayName();
        }

        if (\in_array('phone', $scopes, true) && null !== $account->phone()) {
            $claims += ['phone_number' => $account->phone(), 'phone_number_verified' => $account->phoneVerifiedAt() instanceof DateTimeImmutable];
        }

        return $claims;
    }
}
