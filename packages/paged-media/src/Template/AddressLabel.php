<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;

/**
 * One address label: its lines, as the post expects them in the destination's country — the application formats them.
 */
final readonly class AddressLabel
{
    /** @var non-empty-list<string> */
    public array $lines;

    /**
     * @param list<string> $lines from the recipient's name down to the country; empty lines are left out
     */
    public function __construct(array $lines)
    {
        $kept = array_values(array_filter(array_map(trim(...), $lines), static fn (string $line): bool => '' !== $line));

        if ([] === $kept) {
            throw InvalidDocument::because('An address label has at least one line.');
        }

        $this->lines = $kept;
    }
}
