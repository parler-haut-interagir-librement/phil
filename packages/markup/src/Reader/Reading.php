<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Phil\Ujo\Document;

/**
 * What a reader made of a source: the document, the metadata written apart from the text — the front matter of a
 * Markdown file, what a dialect reads before its text, the title and language of an HTML page —, and what the reader
 * did not keep, in the order of the source, so that a writer sees it before publishing.
 */
final readonly class Reading
{
    /**
     * @param array<array-key, mixed> $metadata
     * @param list<Loss>              $losses
     */
    public function __construct(
        public Document $document,
        public array $metadata = [],
        public array $losses = [],
    ) {
    }
}
