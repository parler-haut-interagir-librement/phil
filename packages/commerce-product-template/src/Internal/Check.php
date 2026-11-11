<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Internal;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * The checks the value objects share: identifiers, codes written in a SKU, paths and channels.
 *
 * @internal
 */
final class Check
{
    /** The characters of a SKU: letters, digits, ".", "_" and "-". */
    public const string SKU_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    /**
     * An identifier: an axis, a face, a zone. Up to 32 lowercase letters, digits or "_", starting with a letter.
     */
    public static function identifier(string $value, string $what): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $value)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not the code of %s: up to 32 lowercase letters, digits or "_", starting with a letter.', $value, $what));
        }

        return $value;
    }

    /**
     * A code written in a SKU: 1 to `$max` letters, digits, ".", "_" or "-", starting with a letter or a digit.
     */
    public static function skuCode(string $value, string $what, int $max): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,'.($max - 1).'}$/D', $value)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not the code of %s: 1 to %d letters, digits, ".", "_" or "-", starting with a letter or digit.', $value, $what, $max));
        }

        return $value;
    }

    /**
     * A path relative to the store of images: segments of letters, digits, ".", "_" or "-", never "." or "..".
     */
    public static function path(string $value, string $what): string
    {
        if (1 !== preg_match('#^(?!.*(?:^|/)\.{1,2}(?:/|$))[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$#D', $value) || \strlen($value) > 512) {
            throw InvalidDefinition::because(\sprintf('"%s" is not the path of %s: up to 512 characters, segments of letters, digits, ".", "_" or "-", relative, never "." nor "..".', $value, $what));
        }

        return $value;
    }

    /**
     * A text a person reads: not blank, at most 255 characters.
     */
    public static function text(string $value, string $what): string
    {
        if ('' === trim($value) || mb_strlen($value) > 255) {
            throw InvalidDefinition::because(\sprintf('%s holds 1 to 255 characters, not blank.', $what));
        }

        return $value;
    }

    /**
     * Channel codes, each 1 to 64 visible ASCII characters, kept once each in the order given.
     *
     * @param list<string> $channels
     *
     * @return list<string>
     */
    public static function channels(array $channels): array
    {
        foreach ($channels as $channel) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $channel)) {
                throw InvalidDefinition::because(\sprintf('"%s" is not a channel: 1 to 64 visible ASCII characters.', $channel));
            }
        }

        return array_values(array_unique($channels));
    }
}
