<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The race on SQLite, two connections to one database file.
 */
#[CoversClass(Polls::class)]
#[CoversClass(DoctrinePollRepository::class)]
final class SqliteClosingRaceTest extends ClosingRaceTestCase
{
    private ?string $file = null;

    #[Override]
    protected function cleanUp(): void
    {
        if (null !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }

        $this->file = null;
    }

    #[Override]
    protected function connect(): Connection
    {
        $this->file ??= tempnam(sys_get_temp_dir(), 'phil-poll-race-').'.sqlite';

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->file]);
    }
}
