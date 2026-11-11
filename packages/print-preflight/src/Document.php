<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * What the preflight checks: the pages of a document, in order, and the fonts it prints with.
 *
 * It describes a PDF written by an application as well as an HTML page laid out by a browser for print: the
 * application fills it from what it placed, or from what the browser measured.
 */
final readonly class Document
{
    /**
     * @param list<Page>     $pages
     * @param list<UsedFont> $fonts
     */
    public function __construct(
        public array $pages,
        public array $fonts = [],
    ) {
    }
}
