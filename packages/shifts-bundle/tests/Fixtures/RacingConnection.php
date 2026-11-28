<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;

/**
 * A connection whose first writes of a shift lose to another request, and which counts the writes it was asked for.
 * It is given to Doctrine as a `wrapperClass`.
 */
final class RacingConnection extends Connection
{
    public int $losses = 0;

    public int $writes = 0;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $types
     */
    #[Override]
    public function update(string $table, array $data, array $criteria = [], array $types = []): int|string
    {
        if (ShiftsTables::SHIFTS === $table) {
            ++$this->writes;

            if ($this->losses > 0) {
                --$this->losses;

                return 0;
            }
        }

        return parent::update($table, $data, $criteria, $types);
    }
}
