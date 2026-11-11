<?php

declare(strict_types=1);

namespace Phil\Markup\Exception;

use InvalidArgumentException;

/**
 * A format no reader knows, asked of a compiler; or a format name an add-on cannot take, already a format or not a
 * name.
 */
final class UnknownFormat extends InvalidArgumentException implements MarkupException
{
    /**
     * @param list<string> $known the formats the compiler knows
     */
    public function __construct(
        string $message,
        public readonly array $known = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $known
     */
    public static function named(string $format, array $known): self
    {
        return new self(\sprintf('The format "%s" is not known; use one of: %s.', $format, implode(', ', $known)), $known);
    }

    public static function taken(string $format): self
    {
        return new self(\sprintf('"%s" cannot name a format of an add-on: a name is 1 to 16 lower-case letters, digits and hyphens, starts with a letter, and is not already a format.', $format));
    }
}
