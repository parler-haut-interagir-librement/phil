<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableEditor;
use Override;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * The register of incidents on a database: every paragraph of section 7 written and read back, the corrections kept,
 * the floor of five years of section 8 held by the purge, and a register of the version before migrated with its rows.
 */
abstract class IncidentRegisterTestCase extends TestCase
{
    private const array TABLES = [PrivacyTables::INCIDENT_REVISIONS, PrivacyTables::INCIDENTS];

    private ?Connection $connection = null;

    private MockClock $clock;

    /**
     * A connection to an empty database, or a skip when the server is not configured.
     */
    abstract protected function open(): Connection;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-20T10:00:00Z');
    }

    #[Override]
    protected function tearDown(): void
    {
        $connection = $this->connection;

        if ($connection instanceof Connection) {
            $this->drop($connection);
            $connection->close();
        }
    }

    public function testEveryParagraphIsWrittenAndReadBack(): void
    {
        $register = $this->register();
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(
            str_repeat('a', 32),
            'List exported to a public folder',
            $at,
            $at->modify('-3 days'),
            [DataCategory::fromString('email'), DataCategory::fromString('person_name')],
            120,
            true,
            'Folder removed',
            $at->modify('+1 day'),
            null,
            null,
            'Names and email addresses of members',
            '',
            $at->modify('-1 day'),
            true,
            $at->modify('+2 hours'),
            true,
            'Addresses of minors, folder indexed: misuse likely.',
            $at->modify('+3 days'),
            PublicNoticeReason::NoContactDetails,
        );
        $register->save($incident);

        $found = $register->find($incident->id);
        self::assertInstanceOf(Incident::class, $found);
        self::assertEquals($incident, $found);
        self::assertSame([], $found->pendingNotifications());
        self::assertSame([], $register->revisions($incident->id), 'A first save corrects nothing.');

        $other = new Incident(str_repeat('b', 32), 'Lost laptop', $at, null, [], null, false, undescribedReason: 'The disk cannot be read back.');
        $register->save($other);
        self::assertEquals($other, $register->find($other->id));
        self::assertSame([$incident->id, $other->id], array_map(static fn (Incident $line): string => $line->id, $register->all()));
        self::assertNull($register->find(str_repeat('0', 32)));
    }

    public function testACorrectionKeepsWhatTheLineHeldBefore(): void
    {
        $register = $this->register();
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('Tpyo', $at, null, [], 12, true);
        $register->save($incident);
        $register->save($incident->update(authorityNotifiedAt: $at->modify('+1 day')));

        $this->clock->modify('+1 hour');
        $corrected = $register->find($incident->id)?->update(summary: 'Typo', affected: 14, clear: ['authority_notified']);
        self::assertInstanceOf(Incident::class, $corrected);
        $register->save($corrected);
        $register->save($corrected);

        self::assertEquals($corrected, $register->find($incident->id));
        self::assertEquals([
            new IncidentRevision($incident->id, 1, 'authority_notified_at', null, new DateTimeImmutable('2026-11-20T10:00:00Z')),
            new IncidentRevision($incident->id, 2, 'affected', '12', new DateTimeImmutable('2026-11-20T11:00:00Z')),
            new IncidentRevision($incident->id, 2, 'authority_notified_at', '2026-11-11 09:00:00', new DateTimeImmutable('2026-11-20T11:00:00Z')),
            new IncidentRevision($incident->id, 2, 'summary', 'Tpyo', new DateTimeImmutable('2026-11-20T11:00:00Z')),
        ], $register->revisions($incident->id));

        $register->save($corrected->update(seriousRisk: false, occurrenceApproximate: false));
        $last = $register->revisions($incident->id)[4] ?? null;
        self::assertEquals(new IncidentRevision($incident->id, 3, 'serious_risk', '1', new DateTimeImmutable('2026-11-20T11:00:00Z')), $last);
        self::assertCount(5, $register->revisions($incident->id));
        self::assertSame([], $register->revisions(str_repeat('0', 32)));
    }

    public function testThePurgeNeverGoesBelowFiveYearsFromTheDiscovery(): void
    {
        $register = $this->register();
        $closed = static fn (string $id, string $discovered, ?string $until = null): Incident => new Incident(
            str_repeat($id, 32),
            $id,
            new DateTimeImmutable($discovered),
            null,
            [],
            null,
            false,
            closedAt: new DateTimeImmutable($until ?? $discovered),
            discoveredUntil: null === $until ? null : new DateTimeImmutable($until),
        );
        $old = $closed('a', '2021-11-20T09:59:59Z');
        $register->save($old);
        $register->save($old->update(measures: 'Corrected'));
        $register->save($closed('b', '2021-11-20T10:00:00Z'));
        $register->save($closed('c', '2021-01-01T00:00:00Z', '2022-06-01T00:00:00Z'));

        $kept = $closed('d', '2023-01-01T00:00:00Z');
        $register->save($kept);
        $register->save($kept->update(measures: 'Corrected too'));
        $register->save(new Incident(str_repeat('e', 32), 'Open', new DateTimeImmutable('2010-01-01T00:00:00Z'), null, [], null, false));

        self::assertSame(0, $register->purge(new PurgeOrder('R-INC', PrivacyTables::INCIDENTS, new DateTimeImmutable('2021-11-20T09:59:59Z'))), 'A cutoff before the floor applies as it is.');
        self::assertSame(1, $register->purge(new PurgeOrder('R-INC', PrivacyTables::INCIDENTS, new DateTimeImmutable('2026-11-20T10:00:00Z'))), 'A rule set too short purges only what is five years old.');
        self::assertSame(['d', 'b', 'c', 'Open'], array_map(static fn (Incident $line): string => $line->summary, $register->all()));
        self::assertSame([], $register->revisions($old->id));
        self::assertCount(1, $register->revisions($kept->id), 'The corrections of a line that stays stay.');
        self::assertEquals(1, $this->connection()->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::INCIDENT_REVISIONS));

        $this->clock->modify('2027-06-01T00:00:01Z');
        self::assertSame(2, $register->purge(new PurgeOrder('R-INC', PrivacyTables::INCIDENTS, new DateTimeImmutable('2030-01-01T00:00:00Z'))), 'The end of the discovery counts.');
        self::assertSame(['d', 'Open'], array_map(static fn (Incident $line): string => $line->summary, $register->all()));
    }

    public function testThePurgeCountsEveryBatchItRemoves(): void
    {
        $register = $this->register();
        $connection = $this->connection();
        $connection->transactional(static function (Connection $connection): void {
            for ($number = 0; $number < 501; ++$number) {
                $connection->insert(PrivacyTables::INCIDENTS, [
                    'id' => str_pad(dechex($number), 32, '0', \STR_PAD_LEFT),
                    'summary' => 'Old',
                    'discovered_at' => '2020-01-01 00:00:00',
                    'categories' => '[]',
                    'serious_risk' => false,
                    'measures' => '',
                    'closed_at' => '2020-01-02 00:00:00',
                ], ['serious_risk' => ParameterType::BOOLEAN]);
            }
        });

        self::assertSame(501, $register->purge(new PurgeOrder('R-INC', PrivacyTables::INCIDENTS, new DateTimeImmutable('2026-11-20T10:00:00Z'))));
        self::assertSame([], $register->all());
    }

    public function testARegisterOfTheVersionBeforeIsMigratedWithItsRowsKept(): void
    {
        $connection = $this->connection ??= $this->open();
        $this->drop($connection);
        $manager = $connection->createSchemaManager();
        $platform = $connection->getDatabasePlatform();
        $before = PrivacyTables::configure(Schema::editor()->create(), $platform)->edit()
            ->dropTableByUnquotedName(PrivacyTables::INCIDENT_REVISIONS)
            ->modifyTableByUnquotedName(PrivacyTables::INCIDENTS, static function (TableEditor $table): void {
                foreach (['information', 'undescribed_reason', 'occurred_until', 'occurrence_approximate', 'discovered_until', 'affected_approximate', 'risk_assessment', 'public_notice_at', 'public_notice_reason'] as $column) {
                    $table->dropColumnByUnquotedName($column);
                }
            })
            ->create();

        foreach ($platform->getCreateTableSQL($before->getTable(PrivacyTables::INCIDENTS)) as $statement) {
            $connection->executeStatement($statement);
        }

        $connection->insert(PrivacyTables::INCIDENTS, [
            'id' => str_repeat('a', 32),
            'summary' => 'Before',
            'discovered_at' => '2026-01-01 00:00:00',
            'categories' => '["email"]',
            'serious_risk' => true,
            'measures' => '',
        ], ['serious_risk' => ParameterType::BOOLEAN]);

        $introspected = $manager->introspectSchema();
        $diff = $manager->createComparator()->compareSchemas($introspected, PrivacyTables::configure($introspected, $platform));

        foreach ($platform->getAlterSchemaSQL($diff) as $statement) {
            if (str_contains($statement, PrivacyTables::INCIDENTS)) {
                $connection->executeStatement($statement);
            }
        }

        $found = new DbalIncidentRegister($connection, $this->clock)->find(str_repeat('a', 32));
        self::assertInstanceOf(Incident::class, $found);
        self::assertSame(['Before', '', '', false, false, '', null], [$found->summary, $found->information, $found->riskAssessment, $found->occurrenceApproximate, $found->affectedApproximate, $found->undescribedReason, $found->publicNoticeReason]);
        self::assertSame([3, 5, 6, 7, 8], $found->missingParagraphs());

        $after = $manager->introspectSchema();
        $remaining = $platform->getAlterSchemaSQL($manager->createComparator()->compareSchemas($after, PrivacyTables::configure($after, $platform)));
        self::assertSame([], array_values(array_filter($remaining, static fn (string $statement): bool => str_contains($statement, PrivacyTables::INCIDENTS.' '))));
    }

    private function register(): DbalIncidentRegister
    {
        $connection = $this->connection ??= $this->open();
        $this->drop($connection);
        $platform = $connection->getDatabasePlatform();
        $schema = PrivacyTables::configure(Schema::editor()->create(), $platform);

        foreach ([PrivacyTables::INCIDENTS, PrivacyTables::INCIDENT_REVISIONS] as $table) {
            foreach ($platform->getCreateTableSQL($schema->getTable($table)) as $statement) {
                $connection->executeStatement($statement);
            }
        }

        return new DbalIncidentRegister($connection, $this->clock);
    }

    private function drop(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
    }

    private function connection(): Connection
    {
        return $this->connection ?? throw new RuntimeException('The register is not created.');
    }
}
