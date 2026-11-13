<?php

declare(strict_types=1);

namespace Phil\SvgBlocks;

use Phil\Ujo\Document;

/**
 * What `BlockMarkup::render()` did to a document: the document with its drawings, the blocks whose drawing waits for
 * a later pass — a diagram, while its service is called in the background —, and the blocks that could not be drawn,
 * each with the reason, for its author.
 */
final readonly class Rendered
{
    /**
     * @param list<string>          $pending  the identifiers of the blocks left for a later pass
     * @param array<string, string> $failures each block that could not be drawn, to the reason
     */
    public function __construct(
        public Document $document,
        public bool $changed,
        public array $pending = [],
        public array $failures = [],
    ) {
    }
}
