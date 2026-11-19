<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Fixture;

use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;

/**
 * A translator whose braille reads back as its text in ASCII braille: each letter its cell, a digit the cell of the
 * letters a to j, `-` the hyphen, `#` the number sign and `,` the capital sign, a space the blank cell. It keeps the
 * batches it was given.
 */
final class LetterTranslator implements Translator
{
    /** @var list<list<string>> */
    public array $batches = [];

    public function __construct(private readonly bool $supported = true)
    {
    }

    public function supports(string $language, Contraction $contraction): bool
    {
        return $this->supported;
    }

    public function translate(array $texts, string $language, Contraction $contraction): array
    {
        $this->batches[] = $texts;

        return array_map(self::braille(...), $texts);
    }

    public static function braille(string $text): string
    {
        $braille = '';

        foreach (str_split(strtoupper($text)) as $character) {
            $braille .= Ascii::cell(ctype_digit($character) ? 'JABCDEFGHI'[(int) $character] : $character);
        }

        return $braille;
    }
}
