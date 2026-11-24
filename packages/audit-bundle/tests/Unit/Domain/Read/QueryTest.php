<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Read;

use DateTimeImmutable;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\ChainState;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditBundle\Domain\Read\SegmentStatus;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditQuery::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(AuditPage::class)]
#[CoversClass(SegmentStatus::class)]
#[CoversClass(ChainState::class)]
#[CoversClass(Order::class)]
final class QueryTest extends TestCase
{
    public function testAQueryIsBuiltOneFilterAtATime(): void
    {
        $all = AuditQuery::all();
        $cursor = Cursor::after($this->row('orders', 3, '2026-11-04 12:00 UTC'));
        $query = $all->byActor('acct-7')
            ->withAction('workflow.order.', true)
            ->about(Subject::of('order', '42'))
            ->inSegment('orders')
            ->inTenant('shop-a')
            ->between(new DateTimeImmutable('2026-11-01 00:00 America/Toronto'), new DateTimeImmutable('2026-11-02 00:00 UTC'))
            ->ordered(Order::OldestFirst)
            ->limitedTo(500)
            ->after($cursor);

        self::assertSame([null, null, false, null, null, null, null, null, null, Order::NewestFirst, 50, null], [$all->actor, $all->action, $all->actionIsPrefix, $all->subjectType, $all->subjectId, $all->segment, $all->tenant, $all->since, $all->until, $all->order, $all->limit, $all->after]);
        self::assertSame(['acct-7', 'workflow.order.', true, 'order', '42', 'orders', 'shop-a', Order::OldestFirst, 500, $cursor], [$query->actor, $query->action, $query->actionIsPrefix, $query->subjectType, $query->subjectId, $query->segment, $query->tenant, $query->order, $query->limit, $cursor]);
        self::assertSame(['2026-11-01T04:00:00.000000Z', '2026-11-02T00:00:00.000000Z'], [$query->sinceInstant(), $query->untilInstant()]);
        self::assertSame('UTC', $query->since?->getTimezone()->getName());
        self::assertSame('UTC', $query->until?->getTimezone()->getName());
        self::assertSame([null, null], [$all->sinceInstant(), $all->untilInstant()]);
        self::assertSame(50, AuditQuery::DEFAULT_LIMIT);
        self::assertSame(500, AuditQuery::MAX_LIMIT);

        $typed = $query->aboutType('invoice');
        self::assertSame(['invoice', null, 'order', '42'], [$typed->subjectType, $typed->subjectId, $query->subjectType, $query->subjectId]);
        self::assertNull($query->inTenant(null)->tenant);
        self::assertFalse($query->withAction('order.paid')->actionIsPrefix);
        self::assertSame(['read.', true], [$all->reads()->action, $all->reads()->actionIsPrefix]);
        self::assertTrue($all->reads()->matches($this->row('members', 1, '2026-11-04 12:00 UTC', Facts::read('account.profile', Subject::of('account', '9')))));
        self::assertFalse($all->reads()->matches($this->row('members', 1, '2026-11-04 12:00 UTC', Facts::of('reader.added'))));
        self::assertNull($all->action);
        self::assertSame(1, $all->limitedTo(1)->limit);

        $history = AuditQuery::historyOf(Subject::of('order', '42'));
        self::assertSame(['order', '42', Order::OldestFirst], [$history->subjectType, $history->subjectId, $history->order]);

        $same = $all->between(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01'));
        self::assertSame($same->sinceInstant(), $same->untilInstant());
        self::assertNull($all->between(null, new DateTimeImmutable('2026-11-01'))->since);
    }

    public function testEachMethodLeavesItsQueryAsItWas(): void
    {
        $base = AuditQuery::all()->byActor('acct-7')->withAction('order.paid')->about(Subject::of('order', '42'))->inSegment('orders')->inTenant('shop-a')->limitedTo(10)->after(Cursor::after($this->row('orders', 3, '2026-11-04 12:00 UTC')));
        $copies = [
            $base->byActor('acct-8'),
            $base->withAction('order.created', true),
            $base->about(Subject::of('invoice', '1')),
            $base->aboutType('invoice'),
            $base->inSegment('members'),
            $base->inTenant('shop-b'),
            $base->between(new DateTimeImmutable('2026-11-01 UTC'), new DateTimeImmutable('2026-11-02 UTC')),
            $base->ordered(Order::OldestFirst),
            $base->limitedTo(20),
            $base->after(null),
        ];

        foreach ($copies as $copy) {
            self::assertNotSame($base, $copy);
        }

        self::assertSame(['acct-7', 'order.paid', false, 'order', '42', 'orders', 'shop-a', null, null, Order::NewestFirst, 10], [$base->actor, $base->action, $base->actionIsPrefix, $base->subjectType, $base->subjectId, $base->segment, $base->tenant, $base->since, $base->until, $base->order, $base->limit]);
        self::assertInstanceOf(Cursor::class, $base->after);
    }

    #[TestWith([0, 'A page holds 1 to 500 rows; 0 is not.'])]
    #[TestWith([501, 'A page holds 1 to 500 rows; 501 is not.'])]
    public function testALimitOutsideItsBoundsIsRefused(int $limit, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        AuditQuery::all()->limitedTo($limit);
    }

    public function testWrongFiltersAreRefused(): void
    {
        foreach ([
            [static fn (): AuditQuery => AuditQuery::all()->between(new DateTimeImmutable('2026-11-02'), new DateTimeImmutable('2026-11-01')), 'The period ends before it starts.'],
            [static fn (): AuditQuery => AuditQuery::all()->inSegment('Orders'), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "Orders" is not.'],
            [static fn (): AuditQuery => AuditQuery::all()->inSegment('a'.str_repeat('b', 64)), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): AuditQuery => AuditQuery::all()->inSegment("a\n"), "A segment is 1 to 64 lower-case letters, digits, \".\", \"_\" or \"-\"; \"a\n\" is not."],
            [static fn (): AuditQuery => AuditQuery::all()->byActor('a@b'), 'An actor is 1 to 128'],
            [static fn (): AuditQuery => AuditQuery::all()->withAction('A'), 'An action is 1 to 64'],
            [static fn (): AuditQuery => AuditQuery::all()->aboutType('A'), 'A subject type is 1 to 64'],
            [static fn (): AuditQuery => AuditQuery::all()->inTenant(''), 'A tenant reference is 1 to 255 bytes'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidValue $invalidValue) {
                self::assertStringStartsWith($message, $invalidValue->getMessage());
            }
        }

        self::assertSame('a'.str_repeat('b', 63), AuditQuery::all()->inSegment('a'.str_repeat('b', 63))->segment);
    }

    public function testARowMatchesEveryFilter(): void
    {
        $row = $this->row('orders', 3, '2026-11-04 12:00:00.5 UTC', Facts::of('workflow.order.ship', 'acct-7', Subject::of('order', '42'), 'shop-a'));
        $bare = $this->row('orders', 4, '2026-11-04 12:00:00.5 UTC');
        $all = AuditQuery::all();

        self::assertTrue($all->matches($row));
        self::assertTrue($all->matches($bare));

        foreach ([
            $all->byActor('acct-7'),
            $all->withAction('workflow.order.ship'),
            $all->withAction('workflow.order.', true),
            $all->withAction('workflow.order.ship', true),
            $all->about(Subject::of('order', '42')),
            $all->aboutType('order'),
            $all->inSegment('orders'),
            $all->inTenant('shop-a'),
            $all->between(new DateTimeImmutable('2026-11-04 12:00:00.5 UTC'), new DateTimeImmutable('2026-11-04 12:00:00.500001 UTC')),
            $all->between(null, new DateTimeImmutable('2026-11-05')),
            $all->between(new DateTimeImmutable('2026-11-04'), null),
        ] as $index => $query) {
            self::assertTrue($query->matches($row), (string) $index);
        }

        foreach ([
            $all->byActor('acct-8'),
            $all->withAction('workflow.order'),
            $all->withAction('workflow.invoice.', true),
            $all->withAction('order', true),
            $all->about(Subject::of('order', '43')),
            $all->about(Subject::of('invoice', '42')),
            $all->aboutType('invoice'),
            $all->inSegment('members'),
            $all->inTenant('shop-b'),
            $all->between(new DateTimeImmutable('2026-11-04 12:00:00.500001 UTC'), null),
            $all->between(null, new DateTimeImmutable('2026-11-04 12:00:00.5 UTC')),
        ] as $index => $query) {
            self::assertFalse($query->matches($row), (string) $index);
        }

        foreach ([$all->byActor('acct-7'), $all->withAction('workflow.', true), $all->aboutType('order'), $all->about(Subject::of('order', '42')), $all->inTenant('shop-a')] as $index => $query) {
            self::assertFalse($query->matches($bare), (string) $index);
        }
    }

    public function testTheCursorComesInTheQuerysOrder(): void
    {
        $at = $this->row('orders', 3, '2026-11-04 12:00 UTC');
        $cursor = Cursor::after($at);
        $newest = AuditQuery::all()->after($cursor);
        $oldest = AuditQuery::all()->ordered(Order::OldestFirst)->after($cursor);

        $earlier = $this->row('orders', 9, '2026-11-04 11:59 UTC');
        $sameInstantEarlierSegment = $this->row('members', 9, '2026-11-04 12:00 UTC');
        $sameInstantEarlierSequence = $this->row('orders', 2, '2026-11-04 12:00 UTC');
        $later = $this->row('members', 1, '2026-11-04 12:01 UTC');
        $sameInstantLaterSegment = $this->row('payments', 1, '2026-11-04 12:00 UTC');
        $sameInstantLaterSequence = $this->row('orders', 4, '2026-11-04 12:00 UTC');

        foreach ([$earlier, $sameInstantEarlierSegment, $sameInstantEarlierSequence] as $row) {
            self::assertTrue($newest->isAfterCursor($row));
            self::assertFalse($oldest->isAfterCursor($row));
        }

        foreach ([$later, $sameInstantLaterSegment, $sameInstantLaterSequence] as $row) {
            self::assertFalse($newest->isAfterCursor($row));
            self::assertTrue($oldest->isAfterCursor($row));
        }

        self::assertFalse($newest->isAfterCursor($at));
        self::assertFalse($oldest->isAfterCursor($at));
        self::assertTrue(AuditQuery::all()->isAfterCursor($at));
    }

    public function testACursorTravelsAsAnOpaqueToken(): void
    {
        $cursor = Cursor::after($this->row('orders.eu', 123456, '2026-11-04 12:00:00.25 UTC'));

        self::assertSame(['2026-11-04T12:00:00.250000Z', 'orders.eu', 123456], [$cursor->instant, $cursor->segment, $cursor->sequence]);
        self::assertSame('2026-11-04T12:00:00.250000Z~orders.eu~123456', $cursor->token());
        self::assertEquals($cursor, Cursor::fromToken($cursor->token()));

        $big = Cursor::fromToken('2026-11-04T12:00:00.250000Z~a~999999999999999999');
        self::assertSame(999999999999999999, $big->sequence);

        foreach ([
            '',
            'nonsense',
            '2026-11-04T12:00:00.250000Z~orders~0',
            '2026-11-04T12:00:00.250000Z~orders~-1',
            '2026-11-04T12:00:00.250000Z~orders~01',
            '2026-11-04T12:00:00.250000Z~Orders~1',
            '2026-11-04T12:00:00Z~orders~1',
            '2026-11-04T12:00:00.250000Z~orders~1~x',
            "2026-11-04T12:00:00.250000Z~orders~1\n",
            'x2026-11-04T12:00:00.250000Z~orders~1',
            '2026-11-04T12:00:00.250000Z~orders~1234567890123456789',
            '2026-11-04T12:00:00.250000Z|orders|1',
        ] as $token) {
            try {
                Cursor::fromToken($token);
                self::fail($token);
            } catch (InvalidValue $invalidValue) {
                self::assertSame('The page cursor is not valid.', $invalidValue->getMessage());
            }
        }

        self::assertSame(1, Cursor::fromToken('2026-11-04T12:00:00.250000Z~orders~1')->sequence);
    }

    public function testAPageKnowsWhetherItIsTheLast(): void
    {
        $row = $this->row('orders', 1, '2026-11-04 12:00 UTC');

        self::assertTrue(new AuditPage([$row], null)->isLast());
        self::assertFalse(new AuditPage([$row], Cursor::after($row))->isLast());
        self::assertSame([$row], new AuditPage([$row], null)->rows);
    }

    public function testASegmentStatusSaysWhetherTheChainHolds(): void
    {
        $head = $this->row('orders', 1, '2026-11-04 12:00 UTC');
        $alarm = new ChainBreak('orders', 2, 'the row was changed after it was written');
        $explained = new ChainBreak('orders', 3, 'explained', explained: true);

        self::assertSame(ChainState::Unverified, new SegmentStatus('orders', $head)->state());
        self::assertSame(ChainState::Holds, new SegmentStatus('orders', $head, [])->state());
        self::assertSame(ChainState::HoldsWithExplainedBreaks, new SegmentStatus('orders', $head, [$explained])->state());
        self::assertSame(ChainState::Broken, new SegmentStatus('orders', $head, [$explained, $alarm])->state());
        self::assertSame(ChainState::Broken, new SegmentStatus('orders', $head, [$alarm, $explained])->state());
        self::assertSame([$alarm], new SegmentStatus('orders', $head, [$explained, $alarm])->alarms());
        self::assertSame([], new SegmentStatus('orders', $head)->alarms());
        self::assertSame(['orders', $head], [new SegmentStatus('orders', $head)->segment, new SegmentStatus('orders', $head)->head]);
        self::assertSame(['unverified', 'holds', 'explained', 'broken'], array_map(static fn (ChainState $state): string => $state->value, ChainState::cases()));
        self::assertSame(['newest', 'oldest'], array_map(static fn (Order $order): string => $order->value, Order::cases()));
    }

    private function row(string $segment, int $sequence, string $at, ?Facts $facts = null): AuditRow
    {
        return AuditRow::restore($segment, $sequence, RowKind::Event, new DateTimeImmutable($at), 'v1', '{}', AuditRow::GENESIS, str_repeat('a', 64), $facts);
    }
}
