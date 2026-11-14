<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Contract;

use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\TableQuery;

/**
 * Where the rows of a table come from.
 */
interface DataSource
{
    /**
     * One page of the rows and how many rows match in all.
     */
    public function fetch(TableQuery $query): ResultPage;

    /**
     * The rows of these identifiers that this source holds: an identifier outside it is left out, which is what keeps
     * an action or an edit inside the table it came from.
     *
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>|object>
     */
    public function find(array $ids): array;
}
