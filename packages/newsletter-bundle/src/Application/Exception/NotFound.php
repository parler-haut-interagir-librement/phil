<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Exception;

use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;

/**
 * A list, a subscriber, a campaign or a template that is not there.
 */
final class NotFound extends RuntimeException implements NewsletterException
{
    public static function of(string $what, string $id): self
    {
        return new self(\sprintf('No %s "%s".', $what, $id));
    }
}
