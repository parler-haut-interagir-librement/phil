<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Database\UtcMoments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalEmailTrackingConsents::class)]
#[CoversClass(UtcMoments::class)]
#[CoversClass(PrivacyTables::class)]
final class DbalEmailTrackingConsentsTest extends TestCase
{
    private Connection $connection;

    private DbalEmailTrackingConsents $store;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->store = new DbalEmailTrackingConsents($this->connection);
    }

    public function testTheTableHoldsOneRowPerMemberAndItsInstantInUtc(): void
    {
        $table = PrivacyTables::configure(Schema::editor()->create())->getTable(PrivacyTables::EMAIL_TRACKING);

        self::assertSame(['subject'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $table->getColumn('decided_at')->getTypeName());
        self::assertSame(DataSubject::MAX_LENGTH, $table->getColumn('subject')->getLength());
        self::assertSame(32, $table->getColumn('version')->getLength());
        self::assertTrue($table->getColumn('opens')->getNotnull());
        self::assertTrue($table->getColumn('clicks')->getNotnull());
        self::assertSame('phil_privacy_email_tracking', $this->store->store());
    }

    public function testAChoiceIsReadBackAndTheLastOneReplacesTheOneBefore(): void
    {
        $subject = DataSubject::fromString('member-1');
        self::assertNull($this->store->find($subject));

        $this->store->save($this->choice('member-1', true, false, '2026-11-14T09:30:00-05:00'));
        $read = $this->store->find($subject);

        self::assertInstanceOf(EmailTrackingChoice::class, $read);
        self::assertTrue($read->opens);
        self::assertFalse($read->clicks);
        self::assertSame('2', $read->version);
        self::assertSame('2026-11-14 14:30:00 +00:00', $read->decidedAt->format('Y-m-d H:i:s P'));
        self::assertSame('2026-11-14 14:30:00', $this->connection->fetchOne('SELECT decided_at FROM '.PrivacyTables::EMAIL_TRACKING));

        $this->store->save($this->choice('member-1', false, true, '2026-11-15T10:00:00Z'));
        $this->store->save($this->choice('member-1', false, true, '2026-11-15T10:00:00Z'));

        $again = $this->store->find($subject);

        self::assertInstanceOf(EmailTrackingChoice::class, $again);
        self::assertFalse($again->opens);
        self::assertTrue($again->clicks);
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::EMAIL_TRACKING));
    }

    public function testForgettingRemovesOnlyThatMember(): void
    {
        $this->store->save($this->choice('member-1', true, true, '2026-11-14T12:00:00Z'));
        $this->store->save($this->choice('member-2', true, true, '2026-11-14T12:00:00Z'));

        $this->store->forget(DataSubject::fromString('member-1'));
        $this->store->forget(DataSubject::fromString('member-1'));

        self::assertNull($this->store->find(DataSubject::fromString('member-1')));
        self::assertInstanceOf(EmailTrackingChoice::class, $this->store->find(DataSubject::fromString('member-2')));
    }

    public function testAnInstantInAnyOtherShapeIsRefused(): void
    {
        $this->store->save($this->choice('member-1', true, true, '2026-11-14T12:00:00Z'));
        $this->connection->executeStatement('UPDATE '.PrivacyTables::EMAIL_TRACKING." SET decided_at = 'yesterday'");

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('The column "decided_at" holds no date and time in the format of its database.');

        $this->store->find(DataSubject::fromString('member-1'));
    }

    public function testTheMomentsAreWrittenAndReadInThePlatformsFormatOnly(): void
    {
        $moment = new DateTimeImmutable('2026-11-14T09:30:15.123456-05:00');

        self::assertSame('2026-11-14 14:30:15', UtcMoments::text($this->connection, $moment));
        self::assertNull(UtcMoments::optionalText($this->connection, null));
        self::assertSame('2026-11-14 14:30:15', UtcMoments::optionalText($this->connection, $moment));
        self::assertSame('2026-11-14T14:30:15.000000+00:00', UtcMoments::read($this->connection, '2026-11-14 14:30:15', 'c')->format('Y-m-d\TH:i:s.uP'));
        self::assertNull(UtcMoments::readOptional($this->connection, null, 'c'));
        self::assertSame('UTC', UtcMoments::readOptional($this->connection, '2026-11-14 14:30:15', 'c')?->getTimezone()->getName());

        foreach (['2026-11-14', '2026-11-14 14:30:15 extra', '', 'now', 1_700_000_000] as $value) {
            try {
                UtcMoments::read($this->connection, $value, 'c');
                self::fail(var_export($value, true).' is not a date and time of the platform.');
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame('The column "c" holds no date and time in the format of its database.', $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testTheFieldsAFormatLeavesOutAreReadAsTheEpochNotAsNow(): void
    {
        $platform = new class extends SQLitePlatform {
            #[Override]
            public function getDateTimeFormatString(): string
            {
                return 'Y-m-d';
            }
        };
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        self::assertSame('2026-11-14T00:00:00+00:00', UtcMoments::read($connection, '2026-11-14', 'c')->format('c'));
    }

    private function choice(string $subject, bool $opens, bool $clicks, string $at): EmailTrackingChoice
    {
        return new EmailTrackingChoice(DataSubject::fromString($subject), '2', $opens, $clicks, new DateTimeImmutable($at));
    }
}
