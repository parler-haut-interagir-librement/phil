<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Override;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\CalendarSync;
use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Contract\BusyStore;
use Phil\CalendarSync\Contract\CalendarDestination;
use Phil\CalendarSync\Contract\ConnectionRepository;
use Phil\CalendarSync\Contract\CredentialStore;
use Phil\CalendarSync\Contract\OutboundEvents;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\Exception\UnknownConnection;
use Phil\CalendarSync\Message\RefreshBusyTimes;
use Phil\CalendarSync\Message\RefreshBusyTimesHandler;
use Phil\CalendarSync\Message\SyncEvent;
use Phil\CalendarSync\Message\SyncEventHandler;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarSync::class)]
#[CoversClass(RefreshBusyTimes::class)]
#[CoversClass(RefreshBusyTimesHandler::class)]
#[CoversClass(SyncEvent::class)]
#[CoversClass(SyncEventHandler::class)]
final class CalendarSyncTest extends TestCase
{
    private Fakes $fakes;

    private CalendarSync $sync;

    #[Override]
    protected function setUp(): void
    {
        $this->fakes = new Fakes();
        $this->sync = new CalendarSync($this->fakes, $this->fakes, $this->fakes, [new FakeSource('ics', []), $this->fakes], [$this->fakes, new FakeSource('other', [])]);
    }

    public function testBusyTimesAreReadThroughTheAdapterOfTheConnectionJoinedAndStored(): void
    {
        $window = SyncWindow::days(new DateTimeImmutable('2026-11-10T00:00:00Z'), 1);
        $stored = $this->sync->refreshBusy('c1', $window);

        self::assertSame([['2026-11-10T09:00:00Z', '2026-11-10T11:00:00Z'], ['2026-11-10T23:00:00Z', '2026-11-11T00:00:00Z']], array_map(static fn (BusyPeriod $period): array => [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z')], $stored));
        self::assertSame([['c1', $window, $stored]], $this->fakes->stored);
        self::assertSame(['credentials:c1', 'busy:c1:bearer'], $this->fakes->calls);
    }

    public function testAFailedReadLeavesTheStoreAsItWas(): void
    {
        $this->fakes->fail = true;

        try {
            $this->sync->refreshBusy('c1', SyncWindow::days(new DateTimeImmutable('2026-11-10T00:00:00Z'), 1));
            self::fail('Read.');
        } catch (ProviderFailed) {
            self::assertSame([], $this->fakes->stored);
        }
    }

    public function testEventsAreWrittenAndRemovedThroughTheAdapterOfTheConnection(): void
    {
        $event = $this->event();
        $this->sync->publish('c1', $event);
        $this->sync->withdraw('c1', 'gone');

        self::assertSame(['credentials:c1', 'put:c1:'.$event->uid, 'credentials:c1', 'remove:c1:gone'], $this->fakes->calls);
    }

    public function testAnUnknownConnectionOrAdapterIsSaidSo(): void
    {
        foreach ([
            ['nope', 'No calendar connection "nope".', 'busy'],
            ['c2', 'No calendar adapter "missing" to read busy times from.', 'busy'],
            ['c2', 'No calendar adapter "missing" to write events to.', 'put'],
            ['c2', 'No calendar adapter "missing" to write events to.', 'remove'],
            ['nope', 'No calendar connection "nope".', 'put'],
        ] as [$id, $message, $what]) {
            try {
                match ($what) {
                    'busy' => $this->sync->refreshBusy($id, SyncWindow::days(new DateTimeImmutable(), 1)),
                    'put' => $this->sync->publish($id, $this->event()),
                    default => $this->sync->withdraw($id, 'u'),
                };
                self::fail('Found.');
            } catch (UnknownConnection $unknownConnection) {
                self::assertSame($message, $unknownConnection->getMessage());
            }
        }

        self::assertSame([], $this->fakes->calls);
    }

    public function testAMessageCarriesIdentifiersAndAWindowOnly(): void
    {
        $message = RefreshBusyTimes::of('c1', new DateTimeImmutable('2026-11-10 00:00', new DateTimeZone('America/Toronto')), 30);

        self::assertSame(['c1', '2026-11-10T05:00:00+00:00', 30], [$message->connectionId, $message->from, $message->days]);
        self::assertEquals(SyncWindow::days(new DateTimeImmutable('2026-11-10T05:00:00Z'), 30), $message->window());

        new RefreshBusyTimesHandler($this->sync)($message);
        self::assertSame('c1', $this->fakes->stored[0][0]);
        self::assertSame('2026-11-10T05:00:00+00:00', $this->fakes->stored[0][1]->from->format('c'));
    }

    public function testAMessageWithAStartWrittenOtherwiseIsRefused(): void
    {
        foreach (['2026-11-10', '2026-02-30T00:00:00+00:00', 'x2026-11-10T00:00:00+00:00'] as $from) {
            try {
                new RefreshBusyTimes('c1', $from, 1)->window();
                self::fail($from);
            } catch (InvalidSync $invalidSync) {
                self::assertSame('The start of a window is written as DateTimeInterface::ATOM writes it.', $invalidSync->getMessage());
            }
        }
    }

    public function testAnEventMessageWritesTheEventAsItIsOrRemovesIt(): void
    {
        $handler = new SyncEventHandler($this->sync, new FakeEvents());
        $handler(new SyncEvent('c1', 'booking-1'));
        $handler(new SyncEvent('c1', 'gone'));

        self::assertSame(['credentials:c1', 'put:c1:booking-1', 'credentials:c1', 'remove:c1:gone'], $this->fakes->calls);
        self::assertSame(['c1', 'gone'], [new SyncEvent('c1', 'gone')->connectionId, new SyncEvent('c1', 'gone')->uid]);
    }

    private function event(string $uid = 'booking-1'): OutboundEvent
    {
        return new OutboundEvent($uid, 'Session', new DateTimeImmutable('2026-11-10T19:00:00Z'), new DateTimeImmutable('2026-11-10T22:00:00Z'));
    }
}

/**
 * The application's side: two connections, credentials, a store, an adapter that reads and writes, and its events.
 */
final class Fakes implements ConnectionRepository, CredentialStore, BusyStore, BusySource, CalendarDestination
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{string, SyncWindow, list<BusyPeriod>}> */
    public array $stored = [];

    public bool $fail = false;

    #[Override]
    public function find(string $id): ?CalendarConnection
    {
        return match (true) {
            'c1' === $id => new CalendarConnection('c1', 'fake', 'primary'),
            'c2' === $id => new CalendarConnection('c2', 'missing', 'primary'),
            default => null,
        };
    }

    #[Override]
    public function credentialsFor(CalendarConnection $connection): Credentials
    {
        $this->calls[] = 'credentials:'.$connection->id;

        return Credentials::bearer('token');
    }

    #[Override]
    public function replace(string $connectionId, SyncWindow $window, array $periods): void
    {
        $this->stored[] = [$connectionId, $window, $periods];
    }

    #[Override]
    public function name(): string
    {
        return 'fake';
    }

    #[Override]
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable
    {
        $this->calls[] = 'busy:'.$connection->id.':'.$credentials->kind;

        if ($this->fail) {
            throw ProviderFailed::status('fake', 500);
        }

        $day = $window->from->format('Y-m-d');

        yield new BusyPeriod(new DateTimeImmutable($day.'T10:00:00Z'), new DateTimeImmutable($day.'T11:00:00Z'));
        yield new BusyPeriod(new DateTimeImmutable($day.'T09:00:00Z'), new DateTimeImmutable($day.'T10:30:00Z'));
        yield new BusyPeriod(new DateTimeImmutable($day.'T23:00:00Z'), new DateTimeImmutable($day.'T23:00:00Z')->modify('+2 days'));
    }

    #[Override]
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void
    {
        $this->calls[] = 'put:'.$connection->id.':'.$event->uid;
    }

    #[Override]
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void
    {
        $this->calls[] = 'remove:'.$connection->id.':'.$uid;
    }
}

/**
 * The application's events: all but `gone`.
 */
final readonly class FakeEvents implements OutboundEvents
{
    #[Override]
    public function find(string $connectionId, string $uid): ?OutboundEvent
    {
        return 'gone' === $uid ? null : new OutboundEvent($uid, 'Session', new DateTimeImmutable('2026-11-10T19:00:00Z'), new DateTimeImmutable('2026-11-10T22:00:00Z'));
    }
}

/**
 * An adapter of another name, that is never asked.
 */
final readonly class FakeSource implements BusySource, CalendarDestination
{
    /**
     * @param list<BusyPeriod> $periods
     */
    public function __construct(
        private string $name,
        private array $periods,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable
    {
        return $this->periods;
    }

    #[Override]
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void
    {
        throw new LogicException('Never asked.');
    }

    #[Override]
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void
    {
        throw new LogicException('Never asked.');
    }
}
