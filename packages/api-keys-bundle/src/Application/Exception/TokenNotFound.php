<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application\Exception;

use Phil\ApiKeysBundle\Domain\Exception\ApiKeysException;
use RuntimeException;

/**
 * No token of that identifier, or none of that owner: another member's token is not found, rather than forbidden.
 */
final class TokenNotFound extends RuntimeException implements ApiKeysException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No token "%s".', $id));
    }
}
