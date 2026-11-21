<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Identifier;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AccountIdGenerator;
use Symfony\Component\Uid\Ulid;

/**
 * A ULID of 26 characters in Crockford's base32, by the Uid component: the identifier of an account unless
 * `accounts.id_generator` names another.
 */
final readonly class UlidAccountIdGenerator implements AccountIdGenerator
{
    #[Override]
    public function generate(): string
    {
        return new Ulid()->toBase32();
    }
}
