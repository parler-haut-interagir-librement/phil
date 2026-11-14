<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Contract;

use Phil\DataTableBundle\Application\State\TablePreference;

/**
 * What the current user chose for a table. Nothing is stored unless the application or phil/settings-bundle provides
 * this.
 */
interface TablePreferences
{
    public function load(string $table): TablePreference;

    public function save(string $table, TablePreference $preference): void;
}
