<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Translation;

use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;
use Phil\BrailleExport\Translation\Internal\Grade1;

/**
 * Uncontracted braille of French and English, in PHP, with no program to install: the letters, the accented letters,
 * the capitals, the numbers and the common punctuation of each code.
 *
 * French follows the Code braille français uniformisé, English Unified English Braille. A letter whose accent the code
 * has no sign for is written as its base letter; any other character without a sign is left out, or stops the
 * translation in strict mode. Contracted braille needs a translator with the tables of the language, such as the
 * liblouis adapter.
 */
final readonly class UncontractedTranslator implements Translator
{
    private Grade1 $french;

    private Grade1 $english;

    /**
     * @param bool $strict whether a character without braille throws {@see \Phil\BrailleExport\Exception\UntranslatableCharacter}
     *                     instead of being left out
     */
    public function __construct(private bool $strict = false)
    {
        $this->french = Grade1::french();
        $this->english = Grade1::english();
    }

    public function supports(string $language, Contraction $contraction): bool
    {
        return Contraction::Uncontracted === $contraction && $this->rules($language) instanceof Grade1;
    }

    public function translate(array $texts, string $language, Contraction $contraction): array
    {
        $rules = $this->rules($language);

        if (Contraction::Uncontracted !== $contraction || !$rules instanceof Grade1) {
            throw UnsupportedTranslation::of($language, $contraction);
        }

        return array_map(fn (string $text): string => $rules->translate($text, $this->strict), $texts);
    }

    private function rules(string $language): ?Grade1
    {
        return match (strtolower(explode('-', str_replace('_', '-', $language))[0])) {
            'fr' => $this->french,
            'en' => $this->english,
            default => null,
        };
    }
}
