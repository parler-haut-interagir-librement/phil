<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Exception;

use RuntimeException;

/**
 * A proof that was read but did not hold: a signature that does not verify, a challenge or an origin that does not
 * match, a counter that went back, a code already used. The message is for logs; a page shows a generic refusal.
 */
final class VerificationFailed extends RuntimeException implements IdentityProviderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
