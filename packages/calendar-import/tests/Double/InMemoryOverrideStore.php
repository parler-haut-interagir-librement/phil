<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Double;

use Override;
use Phil\CalendarImport\Override\EditorOverride;
use Phil\CalendarImport\Override\OverrideStore;

/**
 * The editors' overrides in memory, for the tests only.
 */
final class InMemoryOverrideStore implements OverrideStore
{
    /** @var array<string, list<EditorOverride>> */
    public array $overrides = [];

    #[Override]
    public function forSource(string $sourceId): iterable
    {
        yield from $this->overrides[$sourceId] ?? [];
    }
}
