<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

/**
 * One step of the layout, its texts given by their place in the batch sent to the translator.
 *
 * @internal
 */
final readonly class Entry
{
    public const string TEXT = 'text';

    public const string HEADING = 'heading';

    public const string PAGE_BREAK = 'page';

    public const string SECTION_BREAK = 'section';

    public const string PRINT_PAGE = 'print';

    /**
     * @param list<int> $texts   the texts of the entry, each starting a new line
     * @param int       $indent  the blank cells before the first line of a text
     * @param int       $runover the blank cells before the next lines
     * @param int       $level   the level of a heading
     */
    public function __construct(
        public string $kind,
        public array $texts = [],
        public int $indent = 0,
        public int $runover = 0,
        public int $level = 0,
        public string $raw = '',
    ) {
    }
}
