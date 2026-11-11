<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use RuntimeException;

/**
 * An edit that cannot apply as asked: the flag changed since the operator read it, or a creation names a flag
 * that is already declared.
 */
final class EditConflict extends RuntimeException implements DomainError
{
    public static function staleRevision(FlagName $flag, int $revision): self
    {
        return new self(\sprintf('The flag "%s" changed since revision %d; read it again before editing it.', $flag->value, $revision));
    }

    public static function alreadyDeclared(FlagName $flag): self
    {
        return new self(\sprintf('A flag named "%s" is already declared.', $flag->value));
    }
}
