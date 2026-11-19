<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application\Exception;

use Phil\ApiKeysBundle\Application\CredentialFailure;
use Phil\ApiKeysBundle\Domain\Exception\ApiKeysException;
use RuntimeException;
use Throwable;

/**
 * A presented token that opens nothing: malformed, unknown or with another secret, revoked, or expired. The message
 * never repeats the token.
 */
final class InvalidCredentials extends RuntimeException implements ApiKeysException
{
    public function __construct(
        public readonly CredentialFailure $failure,
        ?Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('The token was refused: %s.', $failure->value), 0, $previous);
    }

    public static function because(CredentialFailure $failure, ?Throwable $previous = null): self
    {
        return new self($failure, $previous);
    }
}
