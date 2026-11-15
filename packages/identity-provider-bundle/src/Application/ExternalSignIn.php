<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The account an external identity signs in, and whether it was created or linked by this sign-in.
 */
final readonly class ExternalSignIn
{
    public function __construct(
        public Account $account,
        public bool $created = false,
        public bool $linked = false,
    ) {
    }
}
