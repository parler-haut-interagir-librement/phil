<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A section, a question or a translation that breaks a rule of the model: the message says which, never the text.
 */
final class InvalidFaq extends InvalidArgumentException implements FaqException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
