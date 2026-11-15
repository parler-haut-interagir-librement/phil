<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Exception;

use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;

/**
 * A link of the newsletter that is not signed by it, is for something else, or expired.
 */
final class LinkRefused extends RuntimeException implements NewsletterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
