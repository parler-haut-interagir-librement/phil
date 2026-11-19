<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Exception;

use RuntimeException;

/**
 * A character the strict uncontracted translator has no braille for.
 */
final class UntranslatableCharacter extends RuntimeException implements TranslationFailed
{
    public function __construct(public readonly string $character, public readonly string $language)
    {
        parent::__construct(\sprintf('The character U+%04X has no braille in the uncontracted table of "%s".', mb_ord($character), $language));
    }
}
