<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Exception;

use Phil\FaqBundle\Domain\Exception\FaqException;
use RuntimeException;

/**
 * No section has this identifier or key.
 */
final class UnknownSection extends RuntimeException implements FaqException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No section is named "%s".', mb_substr($name, 0, 100)));
    }
}
