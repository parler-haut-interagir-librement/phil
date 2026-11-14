<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Contract;

use Phil\DataTableBundle\Application\Definition\TableBuilder;

/**
 * A table of the application: a service, autoconfigured, found by its name. It holds no state of a request: the
 * context comes as an argument, signed so that a browser cannot change it.
 */
interface DataTable
{
    public static function name(): string;

    public function configure(TableBuilder $table): void;

    /**
     * @param array<string, scalar|null> $context what the page gave the component
     */
    public function source(array $context): DataSource;
}
