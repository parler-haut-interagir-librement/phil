<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrivacyTables::class)]
#[CoversClass(DbalAccountDeletions::class)]
#[CoversClass(DbalConsentProofs::class)]
#[CoversClass(PrivacySchemaListener::class)]
final class PrivacyDatabaseTest extends TestCase
{
    private const string A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private Connection $connection;

    private DbalAccountDeletions $deletions;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->deletions = new DbalAccountDeletions($this->connection);
    }

    public function testATableThatExistsIsLeftAlone(): void
    {
        $schema = PrivacyTables::configure(Schema::editor()->create());

        self::assertCount(9, PrivacyTables::configure($schema)->getTables());
    }

    public function testIdentifiersPeopleAndHashesCompareByteForByteOnMySqlAndMariaDb(): void
    {
        $binary = [
            PrivacyTables::DELETIONS => ['id', 'subject', 'export_reference'],
            PrivacyTables::PROOFS => ['id'],
            PrivacyTables::GUEST_REQUESTS => ['id', 'token_hash'],
            PrivacyTables::INCIDENTS => ['id'],
            PrivacyTables::INCIDENT_REVISIONS => ['incident_id', 'field'],
            PrivacyTables::INCIDENT_JOURNAL => ['incident_id'],
            PrivacyTables::EMAIL_TRACKING => ['subject'],
            PrivacyTables::ERASURE_REQUESTS => ['id', 'subject', 'guest_request', 'inventory_version', 'access_hash'],
            PrivacyTables::ERASURE_STEPS => ['request_id', 'module', 'lease', 'idempotency_key'],
        ];

        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            $schema = PrivacyTables::configure(Schema::editor()->create(), $platform);

            foreach ($schema->getTables() as $table) {
                $name = $table->getObjectName()->toString();

                foreach ($table->getColumns() as $column) {
                    $expected = \in_array($column->getObjectName()->toString(), $binary[$name], true) ? ['utf8mb4', 'utf8mb4_bin'] : [null, null];
                    self::assertSame($expected, [$column->getCharset(), $column->getCollation()], $name.'.'.$column->getObjectName()->toString());
                }
            }
        }

        foreach ([null, new PostgreSQLPlatform()] as $platform) {
            foreach (PrivacyTables::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
                foreach ($table->getColumns() as $column) {
                    self::assertSame([null, null], [$column->getCharset(), $column->getCollation()]);
                }
            }
        }
    }

    public function testTheColumnsHoldWhatTheBundleWritesAndRefuseNothingMissing(): void
    {
        $schema = PrivacyTables::configure(Schema::editor()->create());
        $expected = [
            PrivacyTables::DELETIONS => [
                'id' => [32, true],
                'subject' => [DataSubject::MAX_LENGTH, true],
                'place' => [16, true],
                'requested_at' => [null, true],
                'effective_at' => [null, true],
                'closed_at' => [null, false],
                'export_reference' => [32, false],
            ],
            PrivacyTables::PROOFS => [
                'id' => [32, true],
                'version' => [32, true],
                'granted' => [null, true],
                'global_privacy_control' => [null, true],
                'decided_at' => [null, true],
            ],
            PrivacyTables::GUEST_REQUESTS => [
                'id' => [32, true],
                'kind' => [16, true],
                'email' => [null, true],
                'token_hash' => [64, true],
                'requested_at' => [null, true],
                'expires_at' => [null, true],
            ],
            PrivacyTables::INCIDENTS => [
                'id' => [32, true],
                'summary' => [null, true],
                'discovered_at' => [null, true],
                'occurred_at' => [null, false],
                'categories' => [null, true],
                'affected' => [null, false],
                'serious_risk' => [null, true],
                'measures' => [null, true],
                'authority_notified_at' => [null, false],
                'subjects_notified_at' => [null, false],
                'closed_at' => [null, false],
                'information' => [null, false],
                'undescribed_reason' => [null, false],
                'occurred_until' => [null, false],
                'occurrence_approximate' => [null, true],
                'discovered_until' => [null, false],
                'affected_approximate' => [null, true],
                'risk_assessment' => [null, false],
                'public_notice_at' => [null, false],
                'public_notice_reason' => [32, false],
            ],
            PrivacyTables::INCIDENT_REVISIONS => [
                'incident_id' => [32, true],
                'sequence' => [null, true],
                'field' => [32, true],
                'previous' => [null, false],
                'revised_at' => [null, true],
            ],
            PrivacyTables::INCIDENT_JOURNAL => [
                'incident_id' => [32, true],
                'sequence' => [null, true],
                'kind' => [32, true],
                'done_at' => [null, true],
                'party' => [255, true],
                'details' => [null, true],
                'until_at' => [null, false],
                'recorded_at' => [null, true],
            ],
            PrivacyTables::ERASURE_REQUESTS => [
                'id' => [32, true],
                'subject' => [DataSubject::MAX_LENGTH, false],
                'guest_request' => [32, false],
                'inventory_version' => [16, true],
                'state' => [16, true],
                'requested_at' => [null, true],
                'closed_at' => [null, false],
                'access_hash' => [64, false],
            ],
            PrivacyTables::ERASURE_STEPS => [
                'request_id' => [32, true],
                'module' => [64, true],
                'state' => [16, true],
                'attempts' => [null, true],
                'lease' => [32, false],
                'lease_until' => [null, false],
                'idempotency_key' => [97, true],
                'effects' => [null, false],
                'reason' => [32, false],
                'updated_at' => [null, true],
                'completed_at' => [null, false],
            ],
        ];

        foreach ($expected as $table => $columns) {
            self::assertCount(\count($columns), $schema->getTable($table)->getColumns());

            foreach ($columns as $name => [$length, $notNull]) {
                $column = $schema->getTable($table)->getColumn($name);
                self::assertSame([$length, $notNull], [$column->getLength(), $column->getNotnull()], $table.'.'.$name);
            }
        }
    }

    public function testAStepStartsWithoutFailedAttempts(): void
    {
        $default = PrivacyTables::configure(Schema::editor()->create())->getTable(PrivacyTables::ERASURE_STEPS)->getColumn('attempts')->getDefault();

        self::assertSame(0, $default);
    }

    public function testTheFlagsOfAnIncidentReadAsFalseOnTheRowsAlreadyThere(): void
    {
        $table = PrivacyTables::configure(Schema::editor()->create())->getTable(PrivacyTables::INCIDENTS);

        self::assertFalse($table->getColumn('occurrence_approximate')->getDefault());
        self::assertFalse($table->getColumn('affected_approximate')->getDefault());
    }

    public function testASaveChangesItsOwnRequestOnly(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $first = new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Requested, $now, $now);
        $this->deletions->save($first);
        $this->deletions->save(new AccountDeletion(self::B, DataSubject::fromString('m2'), DeletionPlace::Requested, $now, $now));

        $first->setPlace(DeletionPlace::Cancelled);
        $this->deletions->save($first);

        self::assertSame(DeletionPlace::Cancelled, $this->deletions->find(self::A)?->getPlace());
        self::assertSame(DeletionPlace::Requested, $this->deletions->find(self::B)?->getPlace());
    }

    public function testARequestIsWrittenAndReadBackInUtc(): void
    {
        $montreal = new DateTimeImmutable('2026-01-01T12:00:00', new DateTimeZone('America/Montreal'));
        $this->deletions->save(new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Requested, $montreal, $montreal->modify('+30 days')));

        $read = $this->deletions->find(self::A);

        self::assertInstanceOf(AccountDeletion::class, $read);
        self::assertSame('m1', $read->subject->value);
        self::assertSame(DeletionPlace::Requested, $read->getPlace());
        self::assertSame('2026-01-01T17:00:00+00:00', $read->requestedAt->format('c'));
        self::assertSame('2026-01-31T17:00:00+00:00', $read->effectiveAt->format('c'));
        self::assertNull($read->closedAt());
        self::assertNull($read->exportReference());
    }

    public function testAClosedRequestKeepsItsClosureAndItsExport(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $deletion = new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Requested, $now, $now);
        $this->deletions->save($deletion);
        $deletion->setPlace(DeletionPlace::Erased);
        $deletion->exportedTo(self::A);
        $deletion->closeAt($now->modify('+1 day'));

        $this->deletions->save($deletion);

        $read = $this->deletions->find(self::A);

        self::assertNotNull($read);
        self::assertSame(DeletionPlace::Erased, $read->getPlace());
        self::assertSame(self::A, $read->exportReference());
        self::assertSame('2026-01-02', $read->closedAt()?->format('Y-m-d'));
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::DELETIONS);
        self::assertIsNumeric($count);
        self::assertSame(1, (int) $count);
    }

    public function testAnUnknownRequestIsNull(): void
    {
        self::assertNull($this->deletions->find(self::A));
        self::assertNull($this->deletions->openFor(DataSubject::fromString('m1')));
    }

    public function testOnlyAnOpenRequestIsFoundForAPerson(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $this->deletions->save(new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Cancelled, $now, $now));
        self::assertNull($this->deletions->openFor(DataSubject::fromString('m1')));

        $this->deletions->save(new AccountDeletion(self::B, DataSubject::fromString('m1'), DeletionPlace::Exported, $now, $now));
        self::assertSame(self::B, $this->deletions->openFor(DataSubject::fromString('m1'))?->id);
        self::assertNull($this->deletions->openFor(DataSubject::fromString('m2')));
    }

    public function testTheNewestOpenRequestIsAnswered(): void
    {
        $this->deletions->save(new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Requested, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-01-01T00:00:00Z')));
        $this->deletions->save(new AccountDeletion(self::B, DataSubject::fromString('m1'), DeletionPlace::Requested, new DateTimeImmutable('2026-02-01T00:00:00Z'), new DateTimeImmutable('2026-02-01T00:00:00Z')));

        self::assertSame(self::B, $this->deletions->openFor(DataSubject::fromString('m1'))?->id);
    }

    public function testDueRequestsComeOldestFirstWithinTheLimit(): void
    {
        $this->seed(self::A, DeletionPlace::Requested, '2026-02-01T00:00:00Z');
        $this->seed(self::B, DeletionPlace::Exported, '2026-01-15T00:00:00Z');
        $this->seed('cccccccccccccccccccccccccccccccc', DeletionPlace::Erased, '2026-01-01T00:00:00Z');
        $this->seed('dddddddddddddddddddddddddddddddd', DeletionPlace::Requested, '2026-03-01T00:00:00Z');

        $now = new DateTimeImmutable('2026-02-01T00:00:00Z');
        self::assertSame([self::B, self::A], $this->deletions->dueIdentifiers($now, 10));
        self::assertSame([self::B], $this->deletions->dueIdentifiers($now, 1));
    }

    public function testAPurgeRemovesClosedRequestsBeforeTheCutoffOnly(): void
    {
        self::assertSame(PrivacyTables::DELETIONS, $this->deletions->store());
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');

        foreach ([self::A => '2025-01-01T00:00:00Z', self::B => '2026-06-01T00:00:00Z'] as $id => $closed) {
            $deletion = new AccountDeletion($id, DataSubject::fromString('m1'), DeletionPlace::Erased, $now, $now);
            $deletion->closeAt(new DateTimeImmutable($closed));
            $this->deletions->save($deletion);
        }

        $this->deletions->save(new AccountDeletion('cccccccccccccccccccccccccccccccc', DataSubject::fromString('m2'), DeletionPlace::Requested, $now, $now));

        self::assertSame(1, $this->deletions->purge(new PurgeOrder('R', 'x', new DateTimeImmutable('2026-01-01T00:00:00Z'))));
        self::assertNull($this->deletions->find(self::A));
        self::assertNotNull($this->deletions->find(self::B));
        self::assertNotNull($this->deletions->find('cccccccccccccccccccccccccccccccc'));
    }

    public function testACutoffInAnotherZoneIsReadInUtc(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $deletion = new AccountDeletion(self::A, DataSubject::fromString('m1'), DeletionPlace::Erased, $now, $now);
        $deletion->closeAt(new DateTimeImmutable('2026-01-01T02:00:00Z'));

        $this->deletions->save($deletion);

        $cutoff = new DateTimeImmutable('2026-01-01T00:00:00', new DateTimeZone('America/Montreal'));
        self::assertSame(1, $this->deletions->purge(new PurgeOrder('R', 'x', $cutoff)));
    }

    public function testAProofIsDatedAndNamesNoOne(): void
    {
        $proofs = new DbalConsentProofs($this->connection);
        self::assertSame(PrivacyTables::PROOFS, $proofs->store());
        $proofs->record(new ConsentChoice(self::A, '3', ['analytics'], new DateTimeImmutable('2026-01-01T12:00:00', new DateTimeZone('America/Montreal')), true));

        $row = $this->connection->fetchAssociative('SELECT * FROM '.PrivacyTables::PROOFS);

        self::assertIsArray($row);
        self::assertSame(['id', 'version', 'granted', 'global_privacy_control', 'decided_at'], array_keys($row));
        self::assertSame(self::A, $row['id']);
        self::assertSame('3', $row['version']);
        self::assertSame('["analytics"]', $row['granted']);
        self::assertIsNumeric($row['global_privacy_control']);
        self::assertSame(1, (int) $row['global_privacy_control']);
        self::assertSame('2026-01-01 17:00:00', $row['decided_at']);
    }

    public function testAProofWithoutTheGlobalPrivacyControlIsAFalseBoolean(): void
    {
        new DbalConsentProofs($this->connection)->record(new ConsentChoice(self::A, '3', [], new DateTimeImmutable('2026-01-01T00:00:00Z')));

        self::assertSame(0, $this->connection->fetchOne('SELECT global_privacy_control FROM '.PrivacyTables::PROOFS));
    }

    public function testAPurgeRemovesProofsBeforeTheCutoffOnly(): void
    {
        $proofs = new DbalConsentProofs($this->connection);
        $proofs->record(new ConsentChoice(self::A, '1', [], new DateTimeImmutable('2024-01-01T00:00:00Z')));
        $proofs->record(new ConsentChoice(self::B, '1', [], new DateTimeImmutable('2026-01-01T00:00:00Z')));

        $cutoff = new DateTimeImmutable('2025-12-31T19:00:00', new DateTimeZone('America/Montreal'));
        self::assertSame(1, $proofs->purge(new PurgeOrder('R', 'x', $cutoff)));
        self::assertSame(self::B, $this->connection->fetchOne('SELECT id FROM '.PrivacyTables::PROOFS));
    }

    public function testTheListenerAddsTheTablesOnItsOwnConnectionOnly(): void
    {
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($this->connection);

        $own = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new PrivacySchemaListener($this->connection)->postGenerateSchema($own);
        self::assertTrue($own->getSchema()->hasTable(PrivacyTables::PROOFS));

        $other = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new PrivacySchemaListener(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]))->postGenerateSchema($other);
        self::assertFalse($other->getSchema()->hasTable(PrivacyTables::PROOFS));
    }

    public function testTheListenerBuildsTheTablesForThePlatformOfItsConnection(): void
    {
        $mysql = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($mysql);

        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new PrivacySchemaListener($mysql)->postGenerateSchema($event);

        self::assertSame('utf8mb4_bin', $event->getSchema()->getTable(PrivacyTables::DELETIONS)->getColumn('subject')->getCollation());
    }

    private function seed(string $id, DeletionPlace $place, string $effective): void
    {
        $at = new DateTimeImmutable($effective);
        $this->deletions->save(new AccountDeletion($id, DataSubject::fromString('m-'.$id), $place, $at, $at));
    }
}
