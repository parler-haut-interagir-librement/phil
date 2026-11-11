<?php

declare(strict_types=1);

namespace Phil\StructuredData;

/**
 * What a page holds: the graph of its JSON-LD script elements, merged by `@id`, how many there were, and why each
 * element that could not be read was skipped.
 */
final readonly class Extraction
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public Graph $graph,
        public int $blocks,
        public array $errors = [],
    ) {
    }
}
