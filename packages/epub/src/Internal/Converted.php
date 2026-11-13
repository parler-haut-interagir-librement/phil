<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

/**
 * The XHTML of a body, with what was found while writing it: the identifiers, the headings, the page break markers,
 * and whether it holds SVG, MathML or a remote resource.
 *
 * @internal
 */
final readonly class Converted
{
    /**
     * @param list<string>     $ids
     * @param list<Heading>    $headings
     * @param list<PageTarget> $pages
     */
    public function __construct(
        public string $xhtml,
        public array $ids,
        public array $headings,
        public array $pages,
        public bool $svg,
        public bool $mathml,
        public bool $remote,
    ) {
    }
}
