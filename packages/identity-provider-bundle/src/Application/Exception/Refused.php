<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Exception;

use Phil\IdentityProviderBundle\Domain\Exception\IdentityProviderException;
use RuntimeException;

/**
 * An operation the bundle refused, with the translation key of what the page shows: a password too weak or found in
 * a breach, a code that does not match, a method the configuration turned off, the last way to sign in removed.
 */
final class Refused extends RuntimeException implements IdentityProviderException
{
    /**
     * @param list<string> $reasons translation keys, the first the main one
     */
    public function __construct(
        public readonly array $reasons,
    ) {
        parent::__construct(implode(', ', $reasons));
    }

    public static function because(string ...$reasons): self
    {
        return new self(array_values($reasons));
    }
}
