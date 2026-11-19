<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\InvalidBraille;
use Phil\BrailleExport\Translation\Braille;

/**
 * Texts sent to the translator, its answer held to the contract of the port.
 *
 * @internal
 */
final class Translated
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public static function texts(Translator $translator, array $texts, string $language, Contraction $contraction): array
    {
        if ([] === $texts) {
            return [];
        }

        $braille = $translator->translate($texts, $language, $contraction);

        if (\count($braille) !== \count($texts)) {
            throw InvalidBraille::count($translator::class, \count($texts), \count($braille));
        }

        foreach ($braille as $index => $cells) {
            if (!Braille::isBraille($cells)) {
                throw InvalidBraille::notBraille($translator::class, $index);
            }
        }

        return $braille;
    }
}
