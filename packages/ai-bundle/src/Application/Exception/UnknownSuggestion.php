<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use Phil\AiBundle\Domain\Exception\AiException;
use RuntimeException;

/**
 * A decision on a suggestion the journal does not hold: never proposed, or erased with its subject.
 */
final class UnknownSuggestion extends RuntimeException implements AiException
{
    public static function of(string $id): self
    {
        return new self(\sprintf('The journal holds no task "%s".', $id));
    }
}
