<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;

/**
 * An index's name: `pages`, `products_fr`.
 */
final readonly class Index
{
    public static function check(string $index): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $index)) {
            throw InvalidSearch::because(\sprintf('An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $index));
        }

        return $index;
    }
}
