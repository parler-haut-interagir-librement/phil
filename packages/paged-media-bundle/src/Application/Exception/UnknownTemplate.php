<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Exception;

use InvalidArgumentException;
use Phil\PagedMedia\Exception\PagedMediaException;

/**
 * A document template no service is registered under.
 */
final class UnknownTemplate extends InvalidArgumentException implements PagedMediaException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(\sprintf('No document template is named "%s"; the templates are: %s.', $name, [] === $known ? 'none' : implode(', ', $known)));
    }
}
