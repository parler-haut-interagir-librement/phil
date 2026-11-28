<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection whose first-column reads give a list of integers, as a stored value out of its shape would. It is given
 * to Doctrine as a `wrapperClass`.
 */
final class ForeignColumnConnection extends Connection
{
    /**
     * @param list<mixed>            $params
     * @param array<int, int|string> $types
     *
     * @return list<mixed>
     */
    #[Override]
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return [12];
    }
}
