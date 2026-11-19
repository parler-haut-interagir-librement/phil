<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Book;

use Phil\BrailleExport\Layout\PageFormat;

/**
 * A document laid out in braille: its volumes, at least one, the format of its pages and the metadata a file of it carries.
 */
final readonly class Book
{
    /**
     * @param list<Volume> $volumes
     * @param string       $language the BCP 47 tag of the document
     */
    public function __construct(
        public array $volumes,
        public PageFormat $format,
        public string $language,
        public string $title = '',
    ) {
    }

    /**
     * Every page, in order.
     *
     * @return list<Page>
     */
    public function pages(): array
    {
        return array_merge(...array_map(static fn (Volume $volume): array => $volume->pages(), $this->volumes));
    }
}
