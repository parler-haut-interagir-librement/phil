<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

/**
 * A piece of a source document to translate, as a segmenter cuts it: its key, stable as long as the document keeps its
 * structure, and its text.
 */
final readonly class SourceSegment
{
    public function __construct(
        public string $key,
        public string $text,
    ) {
    }
}
