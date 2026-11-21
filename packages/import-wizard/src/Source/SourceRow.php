<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Source;

/**
 * A record of a file: the physical line it starts on — the line a spreadsheet shows, header included, whatever batch
 * reads it —, its cells as written, and whether a cell had text after its closing quote.
 */
final readonly class SourceRow
{
    /**
     * @param list<string> $cells
     */
    public function __construct(
        public int $line,
        public array $cells,
        public bool $malformed = false,
    ) {
    }

    public function isBlank(): bool
    {
        return array_all($this->cells, static fn (string $cell): bool => '' === trim($cell));
    }
}
