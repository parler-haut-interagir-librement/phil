<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\Exception;

use RuntimeException;

/**
 * A text the limits refuse: too long to be read for mentions, or mentioning too many members at once.
 */
final class MentionsRefused extends RuntimeException implements MentionsException
{
    public static function tooMany(int $count, int $limit): self
    {
        return new self(\sprintf('A text mentions %d members at most; this one mentions %d.', $limit, $count));
    }

    public static function tooLong(int $length, int $limit): self
    {
        return new self(\sprintf('A text read for mentions holds %d characters at most; this one holds %d.', $limit, $length));
    }
}
