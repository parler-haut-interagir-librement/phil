<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use JsonException;
use Override;
use PDO;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(DbalIncidentRegister::class)]
final class DbalIncidentRegisterTest extends TestCase
{
    public function testUpdatingAnIncidentLeavesTheOthersAlone(): void
    {
        $connection = $this->connection();
        $register = new DbalIncidentRegister($connection);
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $first = new Incident(str_repeat('a', 32), 'First', $at, null, [], null, false);
        $second = new Incident(str_repeat('b', 32), 'Second', $at, null, [], null, false);
        $register->save($first);
        $register->save($second);

        $register->save($first->update(measures: 'Done'));

        self::assertSame('Done', $register->find($first->id)?->measures);
        self::assertSame('', $register->find($second->id)?->measures);
    }

    public function testTheRiskIsStoredAsABoolean(): void
    {
        $connection = $this->connection();
        $register = new DbalIncidentRegister($connection);
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $register->save(new Incident(str_repeat('a', 32), 'No risk', $at, null, [], null, false));
        $register->save(new Incident(str_repeat('b', 32), 'Risk', $at, null, [], null, true));

        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::INCIDENTS.' WHERE serious_risk = 0'));
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::INCIDENTS.' WHERE serious_risk = 1'));
    }

    public function testCategoriesNestedDeeperThanAListAreUnreadable(): void
    {
        $connection = $this->connection();
        $register = new DbalIncidentRegister($connection);
        $register->save(new Incident(str_repeat('a', 32), 'x', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false));

        $connection->update(PrivacyTables::INCIDENTS, ['categories' => '[["email"]]'], ['id' => str_repeat('a', 32)]);

        $this->expectException(JsonException::class);

        $register->find(str_repeat('a', 32));
    }

    public function testADriverThatReturnsTextStillGivesNumbers(): void
    {
        $connection = $this->connection([PDO::ATTR_STRINGIFY_FETCHES => true]);
        $register = new DbalIncidentRegister($connection);
        $register->save(new Incident(str_repeat('a', 32), 'x', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [DataCategory::fromString('email')], 120, true));

        $found = $register->find(str_repeat('a', 32));

        self::assertInstanceOf(Incident::class, $found);
        self::assertSame(120, $found->affected);
        self::assertTrue($found->seriousRisk);
        self::assertSame('email', $found->categories[0]->value);
    }

    /**
     * @param array<int, mixed> $driverOptions
     */
    private function connection(array $driverOptions = []): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'driverOptions' => $driverOptions]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        return $connection;
    }

    public function testTheNumberOfACorrectionIsAlwaysGiven(): void
    {
        $schema = PrivacyTables::configure(Schema::editor()->create());

        self::assertTrue($schema->getTable(PrivacyTables::INCIDENT_REVISIONS)->getColumn('sequence')->getNotnull());
    }

    public function testTheNumberOfACorrectionTravelsAsAnInteger(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array<mixed>>
             */
            public array $statements = [];

            /**
             * @param array<mixed> $context
             */
            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->statements[] = $context;
            }
        };
        $configuration = new Configuration();
        $configuration->setMiddlewares([new Middleware($logger)]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        $register = new DbalIncidentRegister($connection);
        $incident = new Incident(str_repeat('a', 32), 'Tpyo', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false);
        $register->save($incident);
        $register->save($incident->update(summary: 'Typo'));

        $revisions = array_values(array_filter($logger->statements, static fn (array $context): bool => \is_string($context['sql'] ?? null) && str_starts_with($context['sql'], 'INSERT INTO '.PrivacyTables::INCIDENT_REVISIONS)));
        self::assertCount(1, $revisions);
        self::assertIsArray($revisions[0]['types']);
        self::assertContains(ParameterType::INTEGER, $revisions[0]['types']);
    }
}
