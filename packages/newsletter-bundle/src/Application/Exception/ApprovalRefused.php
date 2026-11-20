<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Exception;

use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;

/**
 * An approval of a campaign the current user may not give.
 */
final class ApprovalRefused extends RuntimeException implements NewsletterException
{
    public static function notGranted(string $role): self
    {
        return new self(\sprintf('Approving a campaign takes the role "%s".', $role));
    }

    public static function anonymous(): self
    {
        return new self('A campaign is approved by a signed-in user.');
    }

    public static function fourEyes(): self
    {
        return new self('Under the four-eyes rule, a campaign is approved by someone other than its author.');
    }
}
