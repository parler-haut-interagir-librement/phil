<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

/**
 * A table of tenant-scoped rows, as SQL writes it — quoted where the mapping quotes it, schema-qualified where the
 * mapping names a schema —, and the column that holds their tenant, as the Doctrine filter writes it.
 */
final readonly class TenantTable
{
    public function __construct(
        public string $table,
        public string $column,
    ) {
    }
}
