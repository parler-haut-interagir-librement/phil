<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the archive refuses: a tag, a page, a state change that cannot happen.
 */
final class InvalidArchiveValue extends InvalidArgumentException implements NewsletterArchiveException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
