<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Definition\TableDefinition;

/**
 * A table opened for a request: its declaration, its data source for this context.
 */
final readonly class OpenTable
{
    public function __construct(
        public TableDefinition $definition,
        public DataSource $source,
    ) {
    }
}
