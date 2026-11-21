<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Identifier;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AccountIdGenerator;
use Symfony\Component\Uid\UuidV7;

/**
 * A UUID of version 7 (RFC 9562) in its 36 characters, by the Uid component: ordered by time, as the keys of an
 * application that uses UUIDv7 elsewhere. Its first 48 bits are the time the account was created; keep it out of
 * `sub` with an opaque or pairwise subject.
 */
final readonly class UuidV7AccountIdGenerator implements AccountIdGenerator
{
    #[Override]
    public function generate(): string
    {
        return new UuidV7()->toRfc4122();
    }
}
