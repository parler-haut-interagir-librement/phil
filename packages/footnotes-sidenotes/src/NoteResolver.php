<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

use Phil\FootnotesSidenotes\Internal\NoteCollector;
use Phil\Ujo\Document;

/**
 * Collects the references and the notes of a UJO (Universal JSON Output) document, in one pass, and numbers the notes
 * in the order of their first reference. The document should be normalized first, which gives its references their
 * canonical form. A note may stand anywhere in the document, nested or not, before or after its references.
 *
 * The time taken grows with the size of the document, not with its square: a book of thousands of notes is one walk.
 * The resolver keeps nothing between calls.
 */
final readonly class NoteResolver
{
    public function __construct(
        private NoteOptions $options = new NoteOptions(),
    ) {
    }

    /**
     * @param ?string $locale the locale whose numbering applies, when the options give one
     */
    public function resolve(Document $document, ?string $locale = null): Resolution
    {
        $collector = new NoteCollector($this->options);

        foreach ($document->blocks as $index => $block) {
            $collector->topLevel($block, $index);
        }

        return $collector->resolution($this->options->numberingFor($locale));
    }
}
