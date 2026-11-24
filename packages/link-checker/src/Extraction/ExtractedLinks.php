<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Extraction;

/**
 * The links of a content in document order, and whether the extractor stopped early: a content past its bytes, which
 * is read up to them, or more links than its limit, of which the first are kept.
 */
final readonly class ExtractedLinks
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        public array $links,
        public bool $truncated = false,
    ) {
    }
}
