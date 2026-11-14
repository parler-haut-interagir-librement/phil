<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use Override;
use Phil\DataTableBundle\Application\Contract\TablePreferences;
use Phil\DataTableBundle\Application\State\TablePreference;

/**
 * Keeps nothing: the choices of a table live in its address only.
 */
final readonly class NoTablePreferences implements TablePreferences
{
    #[Override]
    public function load(string $table): TablePreference
    {
        return new TablePreference();
    }

    #[Override]
    public function save(string $table, TablePreference $preference): void
    {
    }
}
