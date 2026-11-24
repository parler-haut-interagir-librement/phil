<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A feed, an entry or one of their parts that no format could write: a text that is not UTF-8, a field empty or too
 * long, an identifier that is not an absolute IRI, a language that is not a BCP 47 tag. The message quotes at most 40
 * characters of the value.
 */
final class InvalidFeed extends InvalidArgumentException
{
    private const int QUOTED = 40;

    public static function because(string $field, string $rule, string $value): self
    {
        $quoted = mb_check_encoding($value, 'UTF-8') ? mb_substr($value, 0, self::QUOTED) : '(not UTF-8)';

        return new self(\sprintf('The %s "%s" is refused: %s.', $field, $quoted, $rule));
    }

    public static function count(string $field, int $count, int $max): self
    {
        return new self(\sprintf('A feed holds at most %d %s; got %d.', $max, $field, $count));
    }
}
