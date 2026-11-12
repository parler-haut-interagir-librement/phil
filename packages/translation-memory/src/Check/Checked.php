<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;

/**
 * A target and its source, as inline HTML, with their languages and the flags of the segment, as the checks read
 * them.
 */
final readonly class Checked
{
    public function __construct(
        public string $source,
        public string $target,
        public LanguageTag $sourceLanguage,
        public LanguageTag $targetLanguage,
        public Flags $flags = new Flags(),
    ) {
    }

    /**
     * The target of a segment and its source; `null` when the segment has no target.
     */
    public static function segment(Segment $segment, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, Flags $flags = new Flags()): ?self
    {
        return null === $segment->target ? null : new self($segment->source, $segment->target, $sourceLanguage, $targetLanguage, $flags);
    }
}
