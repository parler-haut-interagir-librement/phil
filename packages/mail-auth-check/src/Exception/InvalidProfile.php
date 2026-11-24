<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Exception;

use InvalidArgumentException;

/**
 * A sender profile that asks for more than the package checks at once.
 */
final class InvalidProfile extends InvalidArgumentException implements MailAuthCheckException
{
    public static function tooMany(string $what, int $limit): self
    {
        return new self(\sprintf('A sender profile expects at most %d %s.', $limit, $what));
    }
}
