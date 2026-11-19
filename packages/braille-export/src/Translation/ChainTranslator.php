<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Translation;

use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;

/**
 * The first of several translators that supports the language and the contraction: a translator with full tables
 * first, the {@see UncontractedTranslator} after it for when the tables are missing.
 */
final readonly class ChainTranslator implements Translator
{
    /** @var array<array-key, Translator> */
    private array $translators;

    public function __construct(Translator ...$translators)
    {
        $this->translators = $translators;
    }

    public function supports(string $language, Contraction $contraction): bool
    {
        return $this->first($language, $contraction) instanceof Translator;
    }

    public function translate(array $texts, string $language, Contraction $contraction): array
    {
        $translator = $this->first($language, $contraction) ?? throw UnsupportedTranslation::of($language, $contraction);

        return $translator->translate($texts, $language, $contraction);
    }

    private function first(string $language, Contraction $contraction): ?Translator
    {
        return array_find($this->translators, static fn (Translator $translator): bool => $translator->supports($language, $contraction));
    }
}
