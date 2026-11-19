<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection on which another worker inserts the same group, with a title of its own, just before the first insert;
 * it counts the inserts asked of it.
 */
final class RacingConnection extends Connection
{
    public bool $racing = true;

    public int $inserts = 0;

    #[Override]
    public function insert(string $table, array $data, array $types = []): int|string
    {
        ++$this->inserts;

        if ($this->racing) {
            $this->racing = false;
            parent::insert($table, ['title' => 'Raced', 'times_seen' => 99] + $data, $types);
        }

        return parent::insert($table, $data, $types);
    }
}
