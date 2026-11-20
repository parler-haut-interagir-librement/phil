<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * The port that gives a new account its internal identifier, the key of its row that every other table refers to.
 *
 * An identifier holds 1 to 36 letters, digits and hyphens, as `Account::ID_PATTERN` reads them, and is never given
 * twice. It stays inside the provider: what clients see as `sub` is the subject strategy's, `oidc.subject`.
 */
interface AccountIdGenerator
{
    /**
     * @return non-empty-string
     */
    public function generate(): string;
}
