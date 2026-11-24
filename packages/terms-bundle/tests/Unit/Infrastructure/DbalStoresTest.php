<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalAcceptances;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalPolicyVersions;
use Phil\TermsBundle\Infrastructure\Doctrine\RowReader;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsSchemaListener;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The two stores on SQLite in memory: what is kept and read back, the order, the refusal of a second version of a
 * number, the compliance in batches, the pages of members, the erasure, and the rows that no longer read.
 */
#[CoversClass(DbalPolicyVersions::class)]
#[CoversClass(DbalAcceptances::class)]
#[CoversClass(TermsTables::class)]
#[CoversClass(TermsSchemaListener::class)]
#[CoversClass(RowReader::class)]
final class DbalStoresTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (TermsTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function testAVersionIsKeptAndReadBackTheNewestFirst(): void
    {
        $versions = new DbalPolicyVersions($this->connection);
        $first = new PolicyVersion('terms', 1, ['en' => 'Hello / world', 'fr' => 'Événement'], TextFormat::Text, new DateTimeImmutable('2026-05-01 10:00'), new DateTimeImmutable('2026-05-01 10:00'), true);
        $second = new PolicyVersion('terms', 2, ['en' => '{"blocks":[]}'], TextFormat::Ujo, new DateTimeImmutable('2026-05-02 10:00'), new DateTimeImmutable('2026-06-01 00:00'), false, 'CC-BY-SA-4.0');
        $versions->add($first);
        $versions->add($second);
        $versions->add(new PolicyVersion('privacy', 1, ['en' => 'x'], TextFormat::Text, new DateTimeImmutable(), new DateTimeImmutable(), true));

        self::assertEquals([$second, $first], $versions->all('terms'));
        self::assertSame([], $versions->all('conduct'));
        self::assertSame('{"en":"Hello / world","fr":"Événement"}', $this->connection->fetchOne('SELECT texts FROM phil_terms_version WHERE number = 1'));
        self::assertSame('CC-BY-SA-4.0', $versions->all('terms')[0]->licence);
        self::assertNull($versions->all('terms')[1]->licence);
    }

    public function testASecondVersionOfANumberIsRefused(): void
    {
        $versions = new DbalPolicyVersions($this->connection);
        $version = new PolicyVersion('terms', 1, ['en' => 'x'], TextFormat::Text, new DateTimeImmutable(), new DateTimeImmutable(), true);
        $versions->add($version);

        try {
            $versions->add($version);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('version_taken', $invalidTerms->reason);
            self::assertSame(['%policy%' => 'terms', '%version%' => '1'], $invalidTerms->parameters);
            self::assertSame('Policy "terms" already has a version 1.', $invalidTerms->getMessage());
            self::assertNotNull($invalidTerms->getPrevious());
        }
    }

    public function testARowThatNoLongerReadsIsLeftOut(): void
    {
        $row = ['policy' => 'terms', 'number' => 1, 'format' => 'text', 'texts' => '{"en":"x"}', 'published_at' => '2026-05-01 10:00:00', 'effective_at' => '2026-05-01 10:00:00', 'material' => 1];
        $bad = [
            ['number' => 2, 'format' => 'html'],
            ['number' => 3, 'texts' => 'not json'],
            ['number' => 4, 'texts' => '"a string"'],
            ['number' => 5, 'texts' => '{"en":{"deep":"x"}}'],
            ['number' => 6, 'published_at' => 'not a date'],
            ['number' => 7, 'effective_at' => 'not a date'],
            ['number' => 8, 'material' => 0],
            ['number' => 9, 'policy' => 'Bad'],
            ['number' => 10, 'licence' => 'MIT OR Apache-2.0'],
        ];

        foreach ([[], ...$bad] as $changes) {
            $this->connection->insert(TermsTables::VERSIONS, $changes + $row);
        }

        $read = new DbalPolicyVersions($this->connection)->all('terms');
        self::assertSame([8, 1], array_map(static fn (PolicyVersion $version): int => $version->number, $read));
        self::assertSame([false, true], array_map(static fn (PolicyVersion $version): bool => $version->material, $read));
    }

    public function testAnAcceptanceIsAddedAndReadBackInItsOrder(): void
    {
        $acceptances = new DbalAcceptances($this->connection);
        $first = new Acceptance('42', 'terms', 1, new DateTimeImmutable('2026-05-01 10:00'), AcceptanceChannel::Signup, '192.0.2.0', 'fr-CA', 'a3f1000000000000000000000000000000000000000000000000000000000000');
        $second = new Acceptance('42', 'privacy', 2, new DateTimeImmutable('2026-05-02 10:00'), AcceptanceChannel::Form);
        $acceptances->append($first);
        $acceptances->append(new Acceptance('43', 'terms', 1, new DateTimeImmutable(), AcceptanceChannel::Api));
        $acceptances->append($second);

        self::assertEquals([$first, $second], $acceptances->ofMember('42'));
        self::assertSame([], $acceptances->ofMember('44'));
        self::assertSame(['fr-CA', 'a3f1000000000000000000000000000000000000000000000000000000000000'], array_values((array) $this->connection->fetchAssociative('SELECT locale, text_sha256 FROM phil_terms_acceptance WHERE member = ? AND policy = ?', ['42', 'terms'])));
    }

    public function testAnAcceptanceRecordedBeforeTheProofReadsBackWithout(): void
    {
        $this->connection->insert(TermsTables::ACCEPTANCES, ['member' => '42', 'policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-05-01 10:00:00', 'channel' => 'form']);

        $read = new DbalAcceptances($this->connection)->ofMember('42');

        self::assertCount(1, $read);
        self::assertNull($read[0]->locale);
        self::assertNull($read[0]->textSha256);
    }

    public function testTheProofColumnsAreNullable(): void
    {
        $table = TermsTables::configure(Schema::editor()->create())->getTable(TermsTables::ACCEPTANCES);

        self::assertFalse($table->getColumn('locale')->getNotnull());
        self::assertSame(35, $table->getColumn('locale')->getLength());
        self::assertFalse($table->getColumn('text_sha256')->getNotnull());
        self::assertSame(64, $table->getColumn('text_sha256')->getLength());
        self::assertTrue($table->getColumn('text_sha256')->getFixed());
    }

    public function testTheMembersInOrderWithAPolicy(): void
    {
        $acceptances = new DbalAcceptances($this->connection);
        $acceptances->append(new Acceptance('a', 'terms', 1, new DateTimeImmutable('2026-05-01 10:00'), AcceptanceChannel::Form));
        $acceptances->append(new Acceptance('a', 'terms', 2, new DateTimeImmutable('2026-05-01 10:00'), AcceptanceChannel::Form));
        $acceptances->append(new Acceptance('b', 'terms', 3, new DateTimeImmutable('2026-04-01 10:00'), AcceptanceChannel::Form));
        $acceptances->append(new Acceptance('c', 'privacy', 3, new DateTimeImmutable('2026-05-01 10:00'), AcceptanceChannel::Form));

        self::assertSame(['a', 'b'], $this->sorted($acceptances->compliant('terms', ['a', 'b', 'c'], 2)));
        self::assertSame(['b'], $acceptances->compliant('terms', ['a', 'b', 'c'], 3));
        self::assertSame(['a'], $acceptances->compliant('terms', ['a', 'b', 'c'], 2, new DateTimeImmutable('2026-05-01 10:00')));
        self::assertSame([], $acceptances->compliant('terms', ['a', 'b'], 2, new DateTimeImmutable('2026-05-01 10:00:01')));
        self::assertSame(['a'], $acceptances->compliant('terms', ['a'], 1));
        self::assertSame([], $acceptances->compliant('terms', [], 1));
    }

    public function testTheMomentsAreKeptInUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $versions = new DbalPolicyVersions($this->connection);
            $versions->add(new PolicyVersion('terms', 1, ['en' => 'x'], TextFormat::Text, new DateTimeImmutable('2026-05-01 22:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-06-01 08:30', new DateTimeZone('Europe/Paris')), true));
            $acceptances = new DbalAcceptances($this->connection);
            $acceptances->append(new Acceptance('a', 'terms', 1, new DateTimeImmutable('2026-05-01 23:30', new DateTimeZone('America/Toronto')), AcceptanceChannel::Form));

            self::assertSame(['2026-05-02 02:00:00', '2026-06-01 06:30:00'], array_values((array) $this->connection->fetchAssociative('SELECT published_at, effective_at FROM phil_terms_version')));
            self::assertSame('2026-05-02 03:30:00', $this->connection->fetchOne('SELECT accepted_at FROM phil_terms_acceptance'));

            $version = $versions->all('terms')[0];
            self::assertSame(['2026-05-02T02:00:00+00:00', '2026-06-01T06:30:00+00:00'], [$version->publishedAt->format(\DATE_ATOM), $version->effectiveAt->format(\DATE_ATOM)], 'read back in UTC, whatever the time zone of the server');
            self::assertSame('2026-05-02T03:30:00+00:00', $acceptances->ofMember('a')[0]->acceptedAt->format(\DATE_ATOM));

            // A moment of another time zone is compared as the moment it is.
            self::assertSame(['a'], $acceptances->compliant('terms', ['a'], 1, new DateTimeImmutable('2026-05-02 05:30', new DateTimeZone('Europe/Paris'))));
            self::assertSame([], $acceptances->compliant('terms', ['a'], 1, new DateTimeImmutable('2026-05-02 05:31', new DateTimeZone('Europe/Paris'))));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testTheErasureRemovesTheAcceptancesOfOneMember(): void
    {
        $acceptances = new DbalAcceptances($this->connection);
        $acceptances->append(new Acceptance('42', 'terms', 1, new DateTimeImmutable(), AcceptanceChannel::Form));
        $acceptances->append(new Acceptance('42', 'privacy', 1, new DateTimeImmutable(), AcceptanceChannel::Form));
        $acceptances->append(new Acceptance('43', 'terms', 1, new DateTimeImmutable(), AcceptanceChannel::Form));

        self::assertSame(2, $acceptances->erase('42'));
        self::assertSame(0, $acceptances->erase('42'));
        self::assertSame([], $acceptances->ofMember('42'));
        self::assertCount(1, $acceptances->ofMember('43'));
    }

    public function testAnAcceptanceRowThatNoLongerReadsIsLeftOut(): void
    {
        $row = ['member' => '42', 'policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-05-01 10:00:00', 'channel' => 'form', 'network' => null];

        foreach ([['channel' => 'mail'], ['accepted_at' => 'not a date'], ['policy' => 'Bad'], ['network' => ''], ['version' => 0], ['version' => 'x'], ['member' => '43'], ['locale' => 'French'], ['text_sha256' => 'not a digest'], ['network' => '192.0.2.0']] as $changes) {
            $this->connection->insert(TermsTables::ACCEPTANCES, $changes + $row);
        }

        $read = new DbalAcceptances($this->connection)->ofMember('42');
        self::assertSame([0], array_keys($read));
        self::assertSame('192.0.2.0', $read[0]->network);
    }

    public function testTheTablesAreAddedOnceAndForTheirConnectionOnly(): void
    {
        $schema = TermsTables::configure(Schema::editor()->create());
        self::assertTrue($schema->hasTable(TermsTables::VERSIONS));
        self::assertTrue($schema->hasTable(TermsTables::ACCEPTANCES));
        self::assertCount(2, TermsTables::configure($schema)->getTables());

        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([[$this->connection, true], [$other, false]] as [$connection, $added]) {
            $manager = self::createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($connection);
            $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());

            new TermsSchemaListener($this->connection)->postGenerateSchema($event);

            self::assertSame($added, $event->getSchema()->hasTable(TermsTables::VERSIONS));
            self::assertSame($added, $event->getSchema()->hasTable(TermsTables::ACCEPTANCES));
        }
    }

    public function testTheColumnsAndIndexesOfTheTables(): void
    {
        $schema = TermsTables::configure(Schema::editor()->create());
        $columns = [];
        $names = [
            TermsTables::VERSIONS => ['id', 'policy', 'number', 'format', 'texts', 'published_at', 'effective_at', 'material', 'licence'],
            TermsTables::ACCEPTANCES => ['id', 'member', 'policy', 'version', 'accepted_at', 'channel', 'network', 'locale', 'text_sha256'],
        ];

        foreach ($names as $table => $tableColumns) {
            self::assertCount(\count($tableColumns), $schema->getTable($table)->getColumns());

            foreach ($tableColumns as $name) {
                $column = $schema->getTable($table)->getColumn($name);
                $columns[$table][$name] = [$column->getTypeName(), $column->getLength(), $column->getNotnull(), $column->getAutoincrement()];
            }
        }

        self::assertSame([
            TermsTables::VERSIONS => [
                'id' => ['integer', null, true, true],
                'policy' => ['string', 64, true, false],
                'number' => ['integer', null, true, false],
                'format' => ['string', 8, true, false],
                'texts' => ['text', null, true, false],
                'published_at' => ['datetime_utc_immutable', null, true, false],
                'effective_at' => ['datetime_utc_immutable', null, true, false],
                'material' => ['boolean', null, true, false],
                'licence' => ['string', 64, false, false],
            ],
            TermsTables::ACCEPTANCES => [
                'id' => ['integer', null, true, true],
                'member' => ['string', 190, true, false],
                'policy' => ['string', 64, true, false],
                'version' => ['integer', null, true, false],
                'accepted_at' => ['datetime_utc_immutable', null, true, false],
                'channel' => ['string', 16, true, false],
                'network' => ['string', 64, false, false],
                'locale' => ['string', 35, false, false],
                'text_sha256' => ['string', 64, false, false],
            ],
        ], $columns);

        $sql = implode("\n", $schema->toSql(new SQLitePlatform()));
        self::assertStringContainsString('CREATE UNIQUE INDEX uniq_phil_terms_version_number ON phil_terms_version (policy, number)', $sql);
        self::assertStringContainsString('CREATE INDEX idx_phil_terms_acceptance_member ON phil_terms_acceptance (member, policy, version)', $sql);
        self::assertSame(2, substr_count($sql, 'INTEGER PRIMARY KEY AUTOINCREMENT'));
        self::assertSame(2, substr_count($sql, 'INDEX'));
    }

    public function testEachValueIsReadStrictly(): void
    {
        $read = new RowReader(new SQLitePlatform());
        $row = ['text' => 'a', 'number' => 7, 'digits' => '42', 'digits and a new line' => "42\n", 'long' => str_repeat('9', 19), 'negative' => '-1', 'word' => '4x', 'float' => 1.5, 'moment' => '2026-05-01 10:00:00', 'bad' => 'not a date', 'yes' => 1, 'no' => 0, 'none' => null];

        self::assertSame('a', $read->text($row, 'text'));
        self::assertNull($read->text($row, 'number'));
        self::assertNull($read->text($row, 'missing'));
        self::assertSame(7, $read->number($row, 'number'));
        self::assertSame(42, $read->number($row, 'digits'));
        self::assertNull($read->number($row, 'long'));
        self::assertNull($read->number($row, 'digits and a new line'));
        self::assertNull($read->number($row, 'negative'));
        self::assertNull($read->number($row, 'word'));
        self::assertNull($read->number($row, 'float'));
        self::assertNull($read->number($row, 'missing'));
        self::assertEquals(new DateTimeImmutable('2026-05-01 10:00:00'), $read->moment($row, 'moment'));
        self::assertNull($read->moment($row, 'bad'));
        self::assertNull($read->moment($row, 'none'));
        self::assertNull($read->moment($row, 'missing'));
        self::assertTrue($read->flag($row, 'yes'));
        self::assertFalse($read->flag($row, 'no'));
        self::assertFalse($read->flag($row, 'missing'));
    }

    /**
     * @param list<string> $members
     *
     * @return list<string>
     */
    private function sorted(array $members): array
    {
        sort($members);

        return $members;
    }
}
