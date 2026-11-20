<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Chain;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainVerifier;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * ASVS 5.0, 16.2.2 (time in one zone) and 16.4.1 (log injection): a clock in any time zone writes UTC instants that
 * keep time order and verify after storage, and no fact carries a carriage return, a NUL byte or a terminal escape.
 */
#[CoversClass(AuditTrail::class)]
#[CoversClass(AuditRow::class)]
#[CoversClass(Facts::class)]
#[CoversClass(Subject::class)]
final class AsvsLogIntegrityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function zones(): iterable
    {
        yield 'half an hour behind' => ['America/St_Johns'];
        yield 'fourteen hours ahead' => ['Pacific/Kiritimati'];
        yield 'an offset without a name' => ['-09:30'];
    }

    #[DataProvider('zones')]
    public function testAClockInAnotherZoneWritesUtcInstantsThatVerifyAfterStorage(string $zone): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (AuditTable::configure($connection->createSchemaManager()->introspectSchema(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $rows = new DbalAuditRowRepository($connection);
        $clock = new MockClock('2026-11-04 12:00:00.123456 UTC', $zone);
        $trail = new AuditTrail($rows, new JsonSerializer(), $clock);

        $first = $trail->record('orders', ['order' => 7]);
        $clock->modify('+1 second');
        $second = $trail->record('orders', ['order' => 8]);

        self::assertSame(['UTC', 'UTC'], [$first->recordedAt->getTimezone()->getName(), $second->recordedAt->getTimezone()->getName()]);
        self::assertSame('2026-11-04T12:00:00.123456Z', $first->instant());
        self::assertSame('2026-11-04T12:00:01.123456Z', $second->instant());
        self::assertSame([], new ChainVerifier()->verify('orders', $rows->rows('orders'), []));
    }

    /**
     * The local clock goes back an hour at the end of daylight saving time; the instants, in UTC, keep their order.
     */
    public function testTheEndOfDaylightSavingTimeKeepsTimeOrder(): void
    {
        $before = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-01 01:59:59 EDT'), 'v1', '{}');
        $after = AuditRow::append($before, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-01 01:00:01 EST'), 'v1', '{}');

        self::assertLessThan($after->instant(), $before->instant());
        self::assertSame([], new ChainVerifier()->verify('orders', [$before, $after], []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function controlCharacters(): iterable
    {
        yield 'a carriage return alone' => ["\r"];
        yield 'a NUL byte' => ["\0"];
        yield 'an ANSI escape' => ["\e[31m"];
        yield 'a C1 control' => ["\u{9B}"];
        yield 'a line feed' => ["\n"];
    }

    #[DataProvider('controlCharacters')]
    public function testNoFactCarriesAControlCharacter(string $control): void
    {
        $refused = [];

        foreach ([
            'action' => static fn (): Facts => Facts::of('order.paid'.$control),
            'actor' => static fn (): Facts => Facts::of('order.paid', 'acct-7'.$control),
            'subject type' => static fn (): Subject => Subject::of('order'.$control, 'o-1'),
            'subject id' => static fn (): Subject => Subject::of('order', 'o-1'.$control),
            'tenant' => static fn (): Facts => Facts::of('order.paid', tenant: 'shop'.$control.'a'),
        ] as $fact => $build) {
            try {
                $build();
            } catch (InvalidValue) {
                $refused[] = $fact;
            }
        }

        self::assertSame(['action', 'actor', 'subject type', 'subject id', 'tenant'], $refused);
    }

    #[DataProvider('controlCharacters')]
    public function testARowWhoseStoredFactsCarryAControlCharacterIsNotIntact(string $control): void
    {
        $facts = Facts::restore('order.paid', 'acct-7'.$control, Subject::restore('order', 'o-1'), 'shop-a');
        $row = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00 UTC'), 'v1', '{}', Facts::none());
        $restored = AuditRow::restore('orders', 1, $row->kind, $row->recordedAt, 'v1', '{}', AuditRow::GENESIS, $row->hash, $facts);

        self::assertFalse($facts->isWellFormed());
        self::assertFalse($restored->isIntact());
    }
}
