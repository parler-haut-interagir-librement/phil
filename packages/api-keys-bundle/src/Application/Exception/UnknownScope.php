<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application\Exception;

use InvalidArgumentException;
use Phil\ApiKeysBundle\Domain\Exception\ApiKeysException;

/**
 * A scope the configuration does not declare.
 */
final class UnknownScope extends InvalidArgumentException implements ApiKeysException
{
    /**
     * @param list<string> $declared
     */
    public static function named(string $scope, array $declared): self
    {
        return new self(\sprintf('The scope "%s" is not declared; the declared scopes are: %s.', $scope, [] === $declared ? 'none' : implode(', ', $declared)));
    }
}
