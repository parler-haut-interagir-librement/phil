<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use Phil\TranslationMemory\SegmentState;

/**
 * One unit of an XLIFF file as the library reads and writes it: the key of its segment, its source and target as
 * inline HTML, the state of the target, and a note for translators.
 */
final readonly class XliffUnit
{
    public function __construct(
        public string $key,
        public string $source,
        public ?string $target = null,
        public SegmentState $state = SegmentState::Untranslated,
        public ?string $note = null,
    ) {
    }
}
