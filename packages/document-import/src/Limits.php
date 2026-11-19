<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * How much a document may hold: each entry of an archive, each stream of a PDF, a flat document or a picture once
 * decoded, in `$entryBytes`; an archive, an RTF, PDF, Markdown or HTML file and everything decoded from it together, in
 * `$totalBytes`; how deep groups, objects and forms may nest, in `$depth`; how many objects a PDF may declare, in
 * `$objects`; how many operations reading the pages of a PDF may take, in `$operations` — each content operator, those
 * of a form each time it is drawn, and each run of text a link is checked against. The defaults are far above any
 * text a person writes and far below what a compression bomb, a nesting bomb or a form drawing itself again asks for.
 */
final readonly class Limits
{
    public function __construct(
        public int $entryBytes = 64 * 1024 * 1024,
        public int $totalBytes = 256 * 1024 * 1024,
        public int $depth = 100,
        public int $objects = 500_000,
        public int $operations = 1_000_000,
    ) {
    }
}
