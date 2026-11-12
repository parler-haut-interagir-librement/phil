<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

use Countable;
use Override;
use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\LanguageTag;

/**
 * A store in a PHP array, for tests and for memories loaded whole from a file.
 */
final class InMemoryStore implements MemoryStore, Countable
{
    /**
     * @var array<string, array<string, array<string, MemoryUnit>>> the units by source language, target language and
     *                                                              source fingerprint
     */
    private array $units = [];

    /**
     * @param iterable<MemoryUnit> $units
     */
    public function __construct(iterable $units = [])
    {
        foreach ($units as $unit) {
            $this->add($unit);
        }
    }

    #[Override]
    public function exact(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): iterable
    {
        $unit = $this->units[$source->value][$target->value][$fingerprint->value] ?? null;

        return null === $unit ? [] : [$unit];
    }

    #[Override]
    public function candidates(LanguageTag $source, LanguageTag $target, int $minLength, int $maxLength): iterable
    {
        foreach ($this->units[$source->value][$target->value] ?? [] as $unit) {
            $length = $unit->length();

            if ($length >= $minLength && $length <= $maxLength) {
                yield $unit;
            }
        }
    }

    #[Override]
    public function add(MemoryUnit $unit): void
    {
        $this->units[$unit->sourceLanguage->value][$unit->targetLanguage->value][$unit->fingerprint->value] = $unit;
    }

    #[Override]
    public function count(): int
    {
        $count = 0;

        foreach ($this->units as $targets) {
            foreach ($targets as $units) {
                $count += \count($units);
            }
        }

        return $count;
    }
}
