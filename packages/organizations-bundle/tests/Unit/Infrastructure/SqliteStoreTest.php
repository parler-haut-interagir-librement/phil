<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalRows;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

#[CoversClass(DbalOrganizationStore::class)]
#[CoversClass(DbalRows::class)]
#[CoversClass(OrganizationTables::class)]
final class SqliteStoreTest extends StoreTestCase
{
    private ?Connection $connection = null;

    public function testTheSchemaIsAddedOnce(): void
    {
        $store = $this->store();
        $schema = $store instanceof DbalOrganizationStore ? $store->configureSchema(Schema::editor()->create()) : Schema::editor()->create();

        foreach (OrganizationTables::ALL as $table) {
            self::assertTrue($schema->hasTable($table), $table);
        }

        self::assertCount(8, $schema->getTables());
        self::assertCount(8, OrganizationTables::for($this->connection()->getDatabasePlatform())->configure($schema)->getTables());
    }

    public function testARowOfTheWrongTypeIsRefused(): void
    {
        self::assertSame(12, DbalRows::integer('12'));
        self::assertSame('a', DbalRows::text('a'));

        foreach ([static fn (): string => DbalRows::text(12), static fn (): int => DbalRows::integer('twelve')] as $read) {
            try {
                $read();
                self::fail('A column of the wrong type was read.');
            } catch (UnexpectedValueException) {
            }
        }
    }

    public function testAnOrganizationIsLockedOnlyWhenAskedAndOnlyOnPlatformsThatLock(): void
    {
        $queries = [];
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));
        $connection->method('executeQuery')->willReturnCallback(static function (string $sql, array $params) use (&$queries): Result {
            $queries[] = [$sql, $params];

            return self::createStub(Result::class);
        });
        $store = new DbalOrganizationStore($connection);

        $store->organization(OrganizationId::fromString('o1'));
        $store->organization(OrganizationId::fromString('o1'), false);
        $store->organization(OrganizationId::fromString('o1'), true);

        self::assertCount(3, $queries);
        self::assertStringNotContainsString('FOR UPDATE', $queries[0][0]);
        self::assertStringNotContainsString('FOR UPDATE', $queries[1][0]);
        self::assertStringContainsString('FOR UPDATE', $queries[2][0]);
        self::assertSame(['o1'], array_values($queries[0][1]));
    }

    /**
     * @return iterable<string, array{callable(DbalOrganizationStore): bool}>
     */
    public static function writesCounted(): iterable
    {
        yield 'a member removed' => [static fn (DbalOrganizationStore $store): bool => $store->deleteMembership(OrganizationId::fromString('o1'), MemberId::fromString('ann'))];
        yield 'a team deleted' => [static fn (DbalOrganizationStore $store): bool => $store->deleteTeam(TeamId::fromString('t1'))];
        yield 'a team member removed' => [static fn (DbalOrganizationStore $store): bool => $store->deleteTeamMembership(TeamId::fromString('t1'), MemberId::fromString('ann'))];
        yield 'an invitation used' => [static fn (DbalOrganizationStore $store): bool => $store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@1'))];
        yield 'an invitation revoked' => [static fn (DbalOrganizationStore $store): bool => $store->revokeInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@1'))];
    }

    /**
     * @param callable(DbalOrganizationStore): bool $write
     */
    #[DataProvider('writesCounted')]
    public function testADriverMayCountTheRowsItChangedAsText(callable $write): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('delete')->willReturn('1');
        $connection->method('executeStatement')->willReturn('1');

        self::assertTrue($write(new DbalOrganizationStore($connection)));
    }

    public function testNoUnitsAmongNoIdentifiersCostNoQuery(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::never())->method(self::anything());
        $store = new DbalOrganizationStore($connection);

        self::assertSame([], $store->unitsById([]));
        self::assertSame([], $store->unitMembershipsAmong([], MemberId::fromString('ann')));
    }

    public function testTheMomentOfAnArchiveIsBoundAsAnInteger(): void
    {
        $within = UnitPath::fromString('/a/');
        $parameters = ['o1', '/a/', $within->upperBound()];

        $archive = self::createMock(Connection::class);
        $archive->method('fetchFirstColumn')->willReturn([]);
        $archive->expects(self::once())->method('executeStatement')->with(self::anything(), [3000, ...$parameters], [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING])->willReturn(0);
        new DbalOrganizationStore($archive)->archiveUnits(OrganizationId::fromString('o1'), $within, new DateTimeImmutable('@3000'));

        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER];
        $restore = self::createMock(Connection::class);
        $restore->expects(self::once())->method('fetchFirstColumn')->with(self::anything(), [...$parameters, 3000], $types)->willReturn([]);
        $restore->expects(self::once())->method('executeStatement')->with(self::anything(), [...$parameters, 3000], $types)->willReturn(0);
        new DbalOrganizationStore($restore)->restoreUnits(OrganizationId::fromString('o1'), $within, new DateTimeImmutable('@3000'));
    }

    public function testTheMomentsOfAnInvitationAreBoundAsIntegers(): void
    {
        $revoked = self::createMock(Connection::class);
        $revoked->expects(self::once())->method('executeStatement')->with(self::anything(), [5, 'i1'], [ParameterType::INTEGER, ParameterType::STRING])->willReturn(1);
        self::assertTrue(new DbalOrganizationStore($revoked)->revokeInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@5')));

        $purged = self::createMock(Connection::class);
        $purged->expects(self::once())->method('executeStatement')->with(self::anything(), [5, 5], [ParameterType::INTEGER, ParameterType::INTEGER])->willReturn(2);
        self::assertSame(2, new DbalOrganizationStore($purged)->purgeInvitations(new DateTimeImmutable('@5')));
    }

    #[Override]
    protected function store(): OrganizationStore
    {
        $connection = $this->connection();
        $store = new DbalOrganizationStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
