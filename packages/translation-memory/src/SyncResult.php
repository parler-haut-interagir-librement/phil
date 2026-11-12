<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

/**
 * The segments of a document in one target language after its source was cut again: the segments in the order of the
 * source, and what happened to them.
 */
final readonly class SyncResult
{
    /**
     * @param list<Segment>         $segments the segments of the source, in its order
     * @param list<string>          $added    the keys of the new segments, untranslated
     * @param list<string>          $stale    the keys of the segments whose source changed under their target
     * @param array<string, string> $moved    the new key of each segment found under another key, to its old key
     * @param list<Segment>         $removed  the segments whose text is no longer in the source
     */
    public function __construct(
        public array $segments,
        public array $added = [],
        public array $stale = [],
        public array $moved = [],
        public array $removed = [],
    ) {
    }

    public function hasChanges(): bool
    {
        return [] !== $this->added || [] !== $this->stale || [] !== $this->moved || [] !== $this->removed;
    }
}
