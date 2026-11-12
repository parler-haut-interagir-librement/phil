<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection on which another worker inserts the same row just before the first insert.
 */
final class RacingConnection extends Connection
{
    private bool $raced = false;

    #[Override]
    public function insert(string $table, array $data, array $types = []): int|string
    {
        if (!$this->raced) {
            $this->raced = true;
            parent::insert($table, $data, $types);
        }

        return parent::insert($table, $data, $types);
    }
}
