<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Book;

/**
 * One volume of a braille book: the sections bound together, at least one.
 */
final readonly class Volume
{
    /**
     * @param list<Section> $sections
     */
    public function __construct(public array $sections)
    {
    }

    /**
     * @return list<Page>
     */
    public function pages(): array
    {
        return array_merge(...array_map(static fn (Section $section): array => $section->pages, $this->sections));
    }
}
