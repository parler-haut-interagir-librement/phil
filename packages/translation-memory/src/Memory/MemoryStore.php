<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\LanguageTag;

/**
 * Where translation units are kept. A store answers two questions — the units of a source fingerprint, and the units
 * whose plain source is between two lengths — and the memory scores the answers.
 */
interface MemoryStore
{
    /**
     * The units of one pair of languages whose source has this fingerprint.
     *
     * @return iterable<MemoryUnit>
     */
    public function exact(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): iterable;

    /**
     * The units of one pair of languages whose plain source is from `$minLength` to `$maxLength` characters long.
     *
     * @return iterable<MemoryUnit>
     */
    public function candidates(LanguageTag $source, LanguageTag $target, int $minLength, int $maxLength): iterable;

    /**
     * Keeps a unit; a unit of the same languages and source fingerprint is replaced, the latest translation winning.
     */
    public function add(MemoryUnit $unit): void;
}
