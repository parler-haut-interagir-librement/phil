<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Contract\Translation;

/**
 * The translations `Translations::preload()` read for a set of records along one fallback chain: for each record, the
 * row of the first locale of the chain that has one, or none. It holds what was read and reads nothing more.
 */
final readonly class TranslationSet
{
    /**
     * @param array<int, Translation> $translations by the object identifier of their record
     */
    public function __construct(
        private array $translations,
    ) {
    }

    public function of(Translatable $record): ?Translation
    {
        $translation = $this->translations[spl_object_id($record)] ?? null;

        // An identifier is reused once its object is gone: the record of the row says whether it is this one.
        return $translation?->translatable() === $record ? $translation : null;
    }
}
