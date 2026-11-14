<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use Override;
use Phil\DataTableBundle\Application\Contract\TablePreferences;
use Phil\DataTableBundle\Application\State\TablePreference;

final class MemoryPreferences implements TablePreferences
{
    /** @var array<string, TablePreference> */
    public array $stored = [];

    #[Override]
    public function load(string $table): TablePreference
    {
        return $this->stored[$table] ?? new TablePreference();
    }

    #[Override]
    public function save(string $table, TablePreference $preference): void
    {
        $this->stored[$table] = $preference;
    }
}
