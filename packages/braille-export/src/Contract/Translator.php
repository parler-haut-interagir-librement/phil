<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Contract;

use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\TranslationFailed;
use Phil\BrailleExport\Exception\UnsupportedTranslation;

/**
 * Turns texts into braille cells.
 *
 * Each text is one line of print without a line break; its braille comes back as Unicode braille patterns
 * (U+2800 to U+28FF), one character per cell, a space between words written as the blank cell U+2800. A whole document
 * goes in one call, so that an implementation that starts a program starts it once.
 */
interface Translator
{
    /**
     * Whether texts of that language, a BCP 47 tag such as `fr` or `en-CA`, can be translated with that contraction.
     */
    public function supports(string $language, Contraction $contraction): bool;

    /**
     * @param list<string> $texts the texts, none holding a line break
     *
     * @return list<string> the braille of each text, in the same order and as many
     *
     * @throws UnsupportedTranslation when {@see self::supports()} says no
     * @throws TranslationFailed      when the translation could not be done
     */
    public function translate(array $texts, string $language, Contraction $contraction): array;
}
