<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the newsletter refuses: a list key, a site, a send window, a state change that cannot happen.
 */
final class InvalidNewsletterValue extends InvalidArgumentException implements NewsletterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
