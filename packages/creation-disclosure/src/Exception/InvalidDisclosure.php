<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure\Exception;

use InvalidArgumentException;
use Phil\CreationDisclosure\Part;

/**
 * A code, an address or a stored disclosure that the vocabulary does not hold.
 */
final class InvalidDisclosure extends InvalidArgumentException implements CreationDisclosureException
{
    public static function unknownCode(string $code): self
    {
        return new self(\sprintf('"%s" is not a code of the IPTC Digital Source Type vocabulary.', self::shown($code)));
    }

    public static function unknownUri(string $uri): self
    {
        return new self(\sprintf('"%s" is not an address of the IPTC Digital Source Type vocabulary.', self::shown($uri)));
    }

    public static function unknownPart(string $part): self
    {
        return new self(\sprintf('"%s" is not a part of a work: %s.', self::shown($part), implode(', ', array_column(Part::cases(), 'value'))));
    }

    public static function notACode(string $part): self
    {
        return new self(\sprintf('The source of the part "%s" is a code of the vocabulary, or null when undeclared.', $part));
    }

    public static function noPart(): self
    {
        return new self('A requirement names at least one part of the work.');
    }

    /**
     * At most 80 characters of what was given, so that a message never carries a whole payload.
     */
    private static function shown(string $value): string
    {
        return mb_strlen($value) > 80 ? mb_substr($value, 0, 80).'…' : $value;
    }
}
