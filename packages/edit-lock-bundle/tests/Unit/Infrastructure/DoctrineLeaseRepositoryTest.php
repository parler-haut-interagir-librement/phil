<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Model\LeasedResource;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;

/**
 * The store on SQLite in memory, its table created from the mapping as an application's schema tools create it.
 */
#[CoversClass(DoctrineLeaseRepository::class)]
final class DoctrineLeaseRepositoryTest extends LeaseRepositoryTestCase
{
    #[Override]
    protected function store(): LeaseRepository
    {
        return new DoctrineLeaseRepository($this->connection());
    }

    public function testARowThatIsNoLeaseIsRefused(): void
    {
        $connection = $this->connection();
        $store = new DoctrineLeaseRepository($connection);

        foreach ([
            ['version' => 'x', 'message' => 'The version of a stored lease is a number.'],
            ['version' => 1, 'acquired_at' => 5, 'message' => 'The column "acquired_at" of a stored lease is a text.'],
        ] as $row) {
            $message = $row['message'];
            unset($row['message']);
            $connection->executeStatement('DELETE FROM '.Lease::TABLE);
            $connection->insert(Lease::TABLE, [...['resource' => 'page', 'part' => '', 'holder_id' => 'bob', 'holder_name' => 'Bob', 'acquired_at' => '2026-11-06 15:00:00', 'expires_at' => '2026-11-06 15:00:30'], ...$row]);

            try {
                $store->get(LeaseKey::of('page'));
                self::fail($message);
            } catch (InvalidLease $invalidLease) {
                self::assertSame($message, $invalidLease->getMessage());
            }
        }
    }

    public function testAClaimRunsInATransactionThatLocksTheResourcesRow(): void
    {
        $connection = $this->connection();
        $store = new DoctrineLeaseRepository($connection);
        $lease = Lease::grant(LeaseKey::of('page'), Holder::of('bob', 'Bob'), new DateTimeImmutable('2026-11-06 15:00:00 UTC'), 30);

        self::assertSame('kept', $store->exclusively('page', static function () use ($connection, $store, $lease): string {
            self::assertTrue($connection->isTransactionActive());
            self::assertTrue($store->insert($lease));

            return 'kept';
        }));
        self::assertFalse($connection->isTransactionActive());
        self::assertNull($store->exclusively('page', static fn (): null => null));
        self::assertNotNull($store->get(LeaseKey::of('page')));
        self::assertSame([['resource' => 'page', 'claims' => 2]], $this->resources($connection));

        $store->exclusively('pages', static fn (): bool => true);
        self::assertSame([['resource' => 'page', 'claims' => 2], ['resource' => 'pages', 'claims' => 1]], $this->resources($connection));
    }

    public function testAClaimThatFailsIsUndoneAndAClaimTheDatabaseEndedGivesNothing(): void
    {
        $connection = $this->connection();
        $store = new DoctrineLeaseRepository($connection);
        $driver = static fn (?string $state): AbstractException => new class('refused', $state) extends AbstractException {
        };

        foreach ([
            [new DeadlockException($driver('40P01'), null), true],
            [new LockWaitTimeoutException($driver('HY000'), null), true],
            [new UniqueConstraintViolationException($driver('23000'), null), true],
            [new DriverException($driver('55P03'), null), true],
            [new DriverException($driver('42000'), null), false],
            [new DriverException($driver(null), null), false],
            [new RuntimeException('refused'), false],
        ] as [$failure, $gaveUp]) {
            try {
                $outcome = $store->exclusively('page', static function () use ($store, $failure): never {
                    $store->insert(Lease::grant(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), new DateTimeImmutable('2026-11-06 15:00:00 UTC'), 30));

                    throw $failure;
                });
                self::assertTrue($gaveUp, $failure::class.' is thrown again');
                self::assertNull($outcome);
            } catch (Throwable $throwable) {
                self::assertFalse($gaveUp, $failure::class.' gives nothing');
                self::assertSame($failure, $throwable);
            }

            self::assertNull($store->get(LeaseKey::of('page', 'b1')), 'the claim is undone');
            self::assertFalse($connection->isTransactionActive());
        }

        self::assertSame([], $this->resources($connection), 'the row of the resource is undone with the claim');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resources(Connection $connection): array
    {
        return array_map(static fn (array $row): array => ['resource' => $row['resource'], 'claims' => filter_var($row['claims'], \FILTER_VALIDATE_INT)], $connection->fetchAllAssociative('SELECT resource, claims FROM '.LeasedResource::TABLE.' ORDER BY resource'));
    }

    private function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Lease::class), $entityManager->getClassMetadata(LeasedResource::class)]);

        return $connection;
    }
}
