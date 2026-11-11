<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use PDO;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * SQLite in a file of its own, opened twice: a writer locks the whole database, and the other one, told not to wait,
 * gives up at once.
 */
#[CoversClass(DoctrineLeaseRepository::class)]
#[CoversClass(EditLocks::class)]
final class SqliteConcurrentClaimsTest extends ConcurrentClaimsTestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/phil-edit-lock-'.bin2hex(random_bytes(6)).'.sqlite';
        parent::setUp();
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    #[Override]
    protected function locksTheWholeDatabase(): bool
    {
        return true;
    }

    #[Override]
    protected function connect(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path, 'driverOptions' => [PDO::ATTR_TIMEOUT => 0]]);
    }
}
