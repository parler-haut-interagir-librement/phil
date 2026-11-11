<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Format;

use InvalidArgumentException;
use Phil\Markup\Exception\MarkupException;

/**
 * A format the application did not enable — or no format at all — asked of a Twig filter, of the converter or of a
 * reader.
 */
final class UnavailableFormat extends InvalidArgumentException implements MarkupException
{
    /**
     * @param list<string> $enabled the names of the enabled formats
     */
    public static function named(string $format, array $enabled): self
    {
        return new self(\sprintf('The format "%s" is not one of the enabled formats: %s.', $format, implode(', ', $enabled)));
    }
}
