<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Exception;

use Phil\BrailleExport\Contraction;
use RuntimeException;

/**
 * No translator knows the language with that contraction.
 */
final class UnsupportedTranslation extends RuntimeException implements BrailleExportException
{
    public static function of(string $language, Contraction $contraction): self
    {
        return new self(\sprintf('No translator writes %s braille for the language "%s".', $contraction->value, $language));
    }
}
