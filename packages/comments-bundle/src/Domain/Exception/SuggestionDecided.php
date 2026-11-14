<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Exception;

use RuntimeException;

/**
 * A decision on a suggestion came second: another accept or reject was kept since the thread was read.
 */
final class SuggestionDecided extends RuntimeException implements CommentsException
{
    public static function onThread(string $id): self
    {
        return new self(\sprintf('The suggestion of the thread "%s" was decided meanwhile.', $id));
    }
}
