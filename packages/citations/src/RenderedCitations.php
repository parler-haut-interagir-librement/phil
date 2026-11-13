<?php

declare(strict_types=1);

namespace Phil\Citations;

/**
 * The citations of a text in its style, in the order they were given, and its bibliography in the style's order.
 */
final readonly class RenderedCitations
{
    /**
     * @param list<RichText> $citations
     * @param list<RichText> $bibliography
     */
    public function __construct(
        public array $citations,
        public array $bibliography,
    ) {
    }
}
