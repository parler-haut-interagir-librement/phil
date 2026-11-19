<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Book;

/**
 * The pages of one section of a volume, at least one.
 */
final readonly class Section
{
    /**
     * @param list<Page> $pages
     */
    public function __construct(public array $pages)
    {
    }
}
