<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Exception;

use RuntimeException;

/**
 * A translator that broke the contract of the port: a text that is not Unicode braille, or not one per text given.
 */
final class InvalidBraille extends RuntimeException implements BrailleExportException
{
    public static function notBraille(string $translator, int $index): self
    {
        return new self(\sprintf('%s gave text %d back with characters that are not braille cells.', $translator, $index));
    }

    public static function count(string $translator, int $expected, int $actual): self
    {
        return new self(\sprintf('%s gave %d texts back for %d.', $translator, $actual, $expected));
    }
}
