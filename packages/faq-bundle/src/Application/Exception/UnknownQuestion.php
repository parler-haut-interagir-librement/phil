<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Exception;

use Phil\FaqBundle\Domain\Exception\FaqException;
use RuntimeException;

/**
 * No question has this identifier or anchor.
 */
final class UnknownQuestion extends RuntimeException implements FaqException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No question is named "%s".', mb_substr($name, 0, 100)));
    }
}
