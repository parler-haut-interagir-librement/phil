<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Exception;

use InvalidArgumentException;
use Phil\RelatedContent\Item;

/**
 * An item the library cannot compare: an identifier that is empty, too long or holds control characters, or too many
 * indices or keywords.
 */
final class InvalidItem extends InvalidArgumentException implements RelatedContentException
{
    public static function identifier(string $id): self
    {
        $shown = mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/', '?', $id), 0, 100);

        return new self(\sprintf('"%s" is not an item identifier: 1 to %d bytes, without control characters.', $shown, Item::MAX_ID_LENGTH));
    }

    public static function tooMany(string $what, int $count, int $max): self
    {
        return new self(\sprintf('An item holds %d %s; at most %d are compared.', $count, $what, $max));
    }
}
