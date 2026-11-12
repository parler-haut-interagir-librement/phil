<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Text;

/**
 * The content of an XLIFF file: its languages, the document it translates — `original` — and its units.
 */
final readonly class XliffFile
{
    /**
     * @param list<XliffUnit> $units
     */
    public function __construct(
        public LanguageTag $sourceLanguage,
        public ?LanguageTag $targetLanguage,
        public string $original,
        public array $units = [],
    ) {
    }

    /**
     * The file of the segments of a document, one unit per segment; a stale segment carries the source its target was
     * written for in a note.
     *
     * @param iterable<Segment> $segments
     */
    public static function fromSegments(string $original, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, iterable $segments): self
    {
        $units = [];

        foreach ($segments as $segment) {
            $note = SegmentState::Stale === $segment->state && null !== $segment->translatedSource ? Text::plain($segment->translatedSource) : null;
            $units[] = new XliffUnit($segment->key, $segment->source, $segment->target, $segment->state, $note);
        }

        return new self($sourceLanguage, $targetLanguage, $original, $units);
    }
}
