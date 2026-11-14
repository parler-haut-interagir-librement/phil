<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Chain;

use ArrayIterator;
use DateTimeImmutable;
use Phil\AuditBundle\Domain\Chain\Anchor;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\ChainVerifier;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Exception\ChainConflict;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(AuditRow::class)]
#[CoversClass(ChainVerifier::class)]
#[CoversClass(ChainBreak::class)]
#[CoversClass(ChainConflict::class)]
#[CoversClass(Anchor::class)]
final class ChainTest extends TestCase
{
    public function testRowsAreChained(): void
    {
        $first = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 07:00:00.5 America/Toronto'), 'v1', '{"a":1}');
        $second = AuditRow::append($first, 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 12:01 UTC'), 'v2', '{"b":2}');

        self::assertSame(['orders', 1, RowKind::Event, '2026-11-04T12:00:00.500000Z', 'v1', '{"a":1}', AuditRow::GENESIS], [$first->segment, $first->sequence, $first->kind, $first->recordedAt->format('Y-m-d\TH:i:s.u\Z'), $first->version, $first->payload, $first->previousHash]);
        self::assertSame(hash('sha256', "orders\n1\nevent\n2026-11-04T12:00:00.500000Z\nv1\n".AuditRow::GENESIS."\n\n\n\n\n\n{\"a\":1}"), $first->hash);
        self::assertSame('2026-11-04T12:00:00.500000Z', $first->instant());
        self::assertEquals(Facts::none(), $first->facts);
        self::assertSame([2, $first->hash, RowKind::Discontinuity], [$second->sequence, $second->previousHash, $second->kind]);
        self::assertSame(64, \strlen(AuditRow::GENESIS));
        self::assertSame(str_repeat('0', 64), AuditRow::GENESIS);
        self::assertTrue($first->isIntact());
        self::assertSame('a'.str_repeat('-', 63), AuditRow::append(null, 'a'.str_repeat('-', 63), RowKind::Event, new DateTimeImmutable(), 'v', '')->segment);

        foreach ([
            [static fn (): AuditRow => AuditRow::append(null, '', RowKind::Event, new DateTimeImmutable(), 'v', ''), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "" is not.'],
            [static fn (): AuditRow => AuditRow::append(null, 'Orders', RowKind::Event, new DateTimeImmutable(), 'v', ''), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "Orders" is not.'],
            [static fn (): AuditRow => AuditRow::append(null, '-a', RowKind::Event, new DateTimeImmutable(), 'v', ''), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "-a" is not.'],
            [static fn (): AuditRow => AuditRow::append(null, "a\n", RowKind::Event, new DateTimeImmutable(), 'v', ''), "A segment is 1 to 64 lower-case letters, digits, \".\", \"_\" or \"-\"; \"a\n\" is not."],
            [static fn (): AuditRow => AuditRow::append(null, 'a'.str_repeat('b', 64), RowKind::Event, new DateTimeImmutable(), 'v', ''), 'A segment is 1 to 64 lower-case letters, digits, ".", "_" or "-"; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): AuditRow => AuditRow::append($first, 'billing', RowKind::Event, new DateTimeImmutable(), 'v', ''), 'A row of "billing" cannot follow the head of "orders".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidValue $invalidValue) {
                self::assertSame($message, $invalidValue->getMessage());
            }
        }

        self::assertSame('Row 3 of "orders" was appended by another process.', ChainConflict::at('orders', 3)->getMessage());
    }

    /**
     * The facts are hashed after the previous hash, in a fixed order, and a change to any of them shows.
     */
    public function testTheFactsAreHashed(): void
    {
        $facts = Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'shop-a');
        $row = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00 UTC'), 'v1', '{}', $facts);

        self::assertSame($facts, $row->facts);
        self::assertSame(hash('sha256', "orders\n1\nevent\n2026-11-04T12:00:00.000000Z\nv1\n".AuditRow::GENESIS."\norder.paid\nacct-7\norder\n42\nshop-a\n{}"), $row->hash);
        self::assertTrue($row->isIntact());

        $restore = static fn (Facts $other, string $segment = 'orders', string $version = 'v1'): AuditRow => AuditRow::restore($segment, 1, RowKind::Event, $row->recordedAt, $version, '{}', $row->previousHash, $row->hash, $other);

        self::assertTrue($restore($facts)->isIntact());
        self::assertEquals(Facts::none(), AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v1', '{}', $row->previousHash, $row->hash)->facts);

        foreach ([
            Facts::of('order.refunded', 'acct-7', Subject::of('order', '42'), 'shop-a'),
            Facts::of('order.paid', 'acct-8', Subject::of('order', '42'), 'shop-a'),
            Facts::of('order.paid', 'acct-7', Subject::of('invoice', '42'), 'shop-a'),
            Facts::of('order.paid', 'acct-7', Subject::of('order', '43'), 'shop-a'),
            Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'shop-b'),
            Facts::of('order.paid', 'acct-7', Subject::of('order', '42')),
            Facts::none(),
        ] as $changed) {
            self::assertFalse($restore($changed)->isIntact());
        }

        // A line break stored in a fact or in the segment or the version could move the lines the hash is taken
        // over: such a row is not intact, whatever its hash.
        $shifted = AuditRow::append(null, 'orders', RowKind::Event, $row->recordedAt, 'v1', '{}', Facts::of('order.paid', 'acct-7'));
        $forged = AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v1', '{}', $shifted->previousHash, hash('sha256', "orders\n1\nevent\n2026-11-04T12:00:00.000000Z\nv1\n".AuditRow::GENESIS."\norder.paid\nacct-7\n\n\n\n{}"), Facts::restore("order.paid\nacct-7", null, null, null));
        self::assertFalse($forged->isIntact());
        self::assertTrue($shifted->isIntact());

        foreach ([Facts::restore(null, "a\nb", null, null), Facts::restore(null, null, Subject::restore('Order', '1'), null), Facts::restore(null, null, Subject::restore('order', 'a b'), null), Facts::restore(null, null, null, "a\n")] as $malformed) {
            self::assertFalse($malformed->isWellFormed());
            self::assertFalse(AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v1', '{}', $row->previousHash, $row->hash, $malformed)->isIntact());
        }

        $plain = AuditRow::append(null, 'orders', RowKind::Event, $row->recordedAt, "v\n1", '{}');
        self::assertFalse($plain->isIntact());
        self::assertFalse(AuditRow::restore("orders\nx", 1, RowKind::Event, $row->recordedAt, 'v1', '{}', $row->previousHash, hash('sha256', "orders\nx\n1\nevent\n".$row->instant()."\nv1\n".$row->previousHash."\n\n\n\n\n\n{}"))->isIntact(), 'A line break in the segment alone.');
        self::assertTrue(Facts::restore('order.paid', 'acct-7', Subject::restore('order', '42'), 'shop-a')->isWellFormed());
        self::assertTrue(Facts::none()->isWellFormed());
    }

    public function testAChangeToAnyFieldShows(): void
    {
        $row = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00 UTC'), 'v1', '{"a":1}');
        $restored = AuditRow::restore('orders', 1, RowKind::Event, new DateTimeImmutable('2026-11-04 07:00 America/Toronto'), 'v1', '{"a":1}', AuditRow::GENESIS, $row->hash);

        self::assertTrue($restored->isIntact());
        self::assertSame('2026-11-04T12:00:00+00:00', $restored->recordedAt->format('c'));

        foreach ([
            'segment' => AuditRow::restore('billing', 1, RowKind::Event, $row->recordedAt, 'v1', '{"a":1}', AuditRow::GENESIS, $row->hash),
            'sequence' => AuditRow::restore('orders', 2, RowKind::Event, $row->recordedAt, 'v1', '{"a":1}', AuditRow::GENESIS, $row->hash),
            'kind' => AuditRow::restore('orders', 1, RowKind::Discontinuity, $row->recordedAt, 'v1', '{"a":1}', AuditRow::GENESIS, $row->hash),
            'time' => AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt->modify('+1 microsecond'), 'v1', '{"a":1}', AuditRow::GENESIS, $row->hash),
            'version' => AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v2', '{"a":1}', AuditRow::GENESIS, $row->hash),
            'payload' => AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v1', '{"a":2}', AuditRow::GENESIS, $row->hash),
            'link' => AuditRow::restore('orders', 1, RowKind::Event, $row->recordedAt, 'v1', '{"a":1}', str_repeat('1', 64), $row->hash),
        ] as $field => $changed) {
            self::assertFalse($changed->isIntact(), $field);
        }
    }

    public function testAnIntactSegmentHoldsAgainstItsAnchors(): void
    {
        [$rows, $anchors] = $this->segment();

        self::assertSame([], new ChainVerifier()->verify('orders', $rows, $anchors));
        self::assertSame([], new ChainVerifier()->verify('orders', [], []));
    }

    public function testEveryBreakIsSaid(): void
    {
        [$rows] = $this->segment();
        $tampered = AuditRow::restore('orders', 2, RowKind::Event, $rows[1]->recordedAt, 'v1', '{"amount":999}', $rows[1]->previousHash, $rows[1]->hash);
        $rewritten = AuditRow::append($rows[0], 'orders', RowKind::Event, $rows[1]->recordedAt, 'v1', '{"amount":999}');

        self::assertSame([
            'orders #2: the row was changed after it was written',
        ], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $tampered, $rows[2]], [])));
        self::assertSame([
            'orders #3: the row does not link to the row before it',
        ], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rewritten, $rows[2]], [])));
        self::assertSame([
            'orders #3: row 2 is missing',
            'orders #3: the row does not link to the row before it',
        ], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[2]], [])));
        self::assertSame([
            'orders #2: row 1 is missing',
            'orders #2: the row does not link to the row before it',
        ], $this->said(new ChainVerifier()->verify('orders', [$rows[1], $rows[2]], [])));
    }

    public function testAnAnchorNoRowMatchesIsARewrite(): void
    {
        [$rows] = $this->segment();
        $at = new DateTimeImmutable('2026-11-04 13:00 UTC');
        $rewritten = [$rows[0], AuditRow::append($rows[0], 'orders', RowKind::Event, $rows[1]->recordedAt, 'v1', '{"amount":999}')];

        self::assertSame(['orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00'], $this->said(new ChainVerifier()->verify('orders', $rewritten, [new Anchor('orders', $at, $rows[0]->hash, $rows[1]->hash)])));
        self::assertSame(['orders: the anchor of 2026-11-04T13:00:00+00:00 does not start where the chain starts'], $this->said(new ChainVerifier()->verify('orders', $rows, [new Anchor('orders', $at, str_repeat('f', 64), $rows[1]->hash)])));
        self::assertSame(['orders: the anchor of 2026-11-04T13:00:00+00:00 does not start where the chain starts'], $this->said(new ChainVerifier()->verify('orders', [], [new Anchor('orders', $at, $rows[0]->hash, $rows[1]->hash)])));
    }

    public function testADiscontinuityAnchoredAfterTheLostAnchorExplainsARewind(): void
    {
        [$rows] = $this->segment();
        $lost = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), $rows[0]->hash, $rows[2]->hash);
        $restore = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 13:00:01 UTC'), 'v1', '{"discontinuity":"restore"}');
        $covering = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:05 UTC'), $rows[0]->hash, $restore->hash);
        $restored = [$rows[0], $rows[1], $restore];

        $breaks = new ChainVerifier()->verify('orders', $restored, [$lost, $covering]);
        self::assertSame(['orders #3: no row has the head hash anchored at 2026-11-04T13:00:00+00:00: explained by this discontinuity'], $this->said($breaks));
        self::assertTrue($breaks[0]->explained);
        self::assertFalse($breaks[0]->isAlarm());
        self::assertSame([], new ChainVerifier()->verify('orders', $restored, []), 'Without anchors, nothing is compared.');
    }

    public function testADiscontinuityNoAnchorCoversExplainsNothingAndIsAnAlarm(): void
    {
        [$rows] = $this->segment();
        $lost = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), $rows[0]->hash, $rows[2]->hash);
        $restore = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-05 09:00 UTC'), 'v1', '{"discontinuity":"restore"}');

        $breaks = new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $restore], [$lost]);

        self::assertSame([
            'orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00',
            'orders #3: the discontinuity is covered by no anchor',
        ], $this->said($breaks));
        self::assertTrue($breaks[0]->isAlarm());
        self::assertTrue($breaks[1]->isAlarm());

        $covered = new Anchor('orders', new DateTimeImmutable('2026-11-04 12:15 UTC'), $rows[0]->hash, $rows[1]->hash);
        self::assertSame(['orders #3: the discontinuity is covered by no anchor'], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $restore], [$covered])), 'An anchor of a row before the discontinuity does not cover it.');
    }

    public function testOnlyADiscontinuityBetweenTheLostAnchorAndTheNextOneThatHoldsExplainsIt(): void
    {
        [$rows] = $this->segment();
        $lost = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), $rows[0]->hash, $rows[2]->hash);

        $tooEarly = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 13:00 UTC'), 'v1', '{"discontinuity":"restore"}');
        self::assertSame(['orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00'], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $tooEarly], [$lost, new Anchor('orders', new DateTimeImmutable('2026-11-04 13:05 UTC'), $rows[0]->hash, $tooEarly->hash)])));

        $event = AuditRow::append($rows[1], 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 14:00 UTC'), 'v1', '{}');
        self::assertSame(['orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00'], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $event], [$lost, new Anchor('orders', new DateTimeImmutable('2026-11-04 14:05 UTC'), $rows[0]->hash, $event->hash)])));

        $late = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 14:00 UTC'), 'v1', '{"discontinuity":"restore"}');
        $heldInBetween = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:30 UTC'), $rows[0]->hash, $rows[1]->hash);
        $covering = new Anchor('orders', new DateTimeImmutable('2026-11-04 14:05 UTC'), $rows[0]->hash, $late->hash);
        self::assertSame(['orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00'], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $late], [$covering, $lost, $heldInBetween])), 'An anchor held after the lost one, before the discontinuity: the discontinuity came too late.');
        self::assertSame(['orders #3: no row has the head hash anchored at 2026-11-04T13:00:00+00:00: explained by this discontinuity'], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $late], [$covering, $lost])), 'The anchors are taken in time order, whatever order they are read in.');
    }

    public function testEveryAnchorIsCheckedAfterOneThatDoesNotStartWhereTheChainStarts(): void
    {
        [$rows] = $this->segment();
        $anchors = [new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), str_repeat('f', 64), $rows[1]->hash), new Anchor('orders', new DateTimeImmutable('2026-11-04 13:30 UTC'), $rows[0]->hash, str_repeat('e', 64))];

        self::assertSame([
            'orders: the anchor of 2026-11-04T13:00:00+00:00 does not start where the chain starts',
            'orders: no row has the head hash anchored at 2026-11-04T13:30:00+00:00',
        ], $this->said(new ChainVerifier()->verify('orders', $rows, $anchors)));
    }

    public function testAHeadIsFoundAtTheFirstRowThatHasIt(): void
    {
        [$rows] = $this->segment();
        $lost = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), $rows[0]->hash, $rows[2]->hash);
        $restore = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 13:00:01 UTC'), 'v1', '{"discontinuity":"restore"}');
        $copy = AuditRow::restore('orders', 4, RowKind::Event, new DateTimeImmutable('2026-11-04 13:01 UTC'), 'v1', '{}', $restore->hash, $rows[1]->hash);
        $next = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:05 UTC'), $rows[0]->hash, $rows[1]->hash);

        self::assertSame([
            'orders #4: the row was changed after it was written',
            'orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00',
            'orders #3: the discontinuity is covered by no anchor',
        ], $this->said(new ChainVerifier()->verify('orders', [$rows[0], $rows[1], $restore, $copy], [$lost, $next])), 'A later copy of an anchored row covers nothing after the row.');
    }

    public function testTheNextAnchorIsTheFirstThatHoldsAfterTheLostOne(): void
    {
        [$rows] = $this->segment();
        $lost = new Anchor('orders', new DateTimeImmutable('2026-11-04 13:00 UTC'), $rows[0]->hash, $rows[2]->hash);
        $restore = AuditRow::append($rows[1], 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 13:45 UTC'), 'v1', '{"discontinuity":"restore"}');
        $restored = [$rows[0], $rows[1], $restore];
        $covering = new Anchor('orders', new DateTimeImmutable('2026-11-04 14:05 UTC'), $rows[0]->hash, $restore->hash);
        $explained = 'orders #3: no row has the head hash anchored at 2026-11-04T13:00:00+00:00: explained by this discontinuity';

        self::assertSame([$explained], $this->said(new ChainVerifier()->verify('orders', $restored, [$lost, new Anchor('orders', $lost->periodEnd, $rows[0]->hash, $rows[1]->hash), $covering])), 'An anchor of the same period as the lost one is not after it.');
        self::assertSame([
            $explained,
            'orders #3: no row has the head hash anchored at 2026-11-04T13:30:00+00:00: explained by this discontinuity',
        ], $this->said(new ChainVerifier()->verify('orders', $restored, [$lost, new Anchor('orders', new DateTimeImmutable('2026-11-04 13:30 UTC'), $rows[0]->hash, str_repeat('e', 64)), $covering])), 'An anchor lost too is not one that holds.');
        self::assertSame([
            'orders: no row has the head hash anchored at 2026-11-04T13:00:00+00:00',
        ], $this->said(new ChainVerifier()->verify('orders', $restored, [$lost, new Anchor('orders', $covering->periodEnd, $rows[0]->hash, $rows[1]->hash), $covering])), 'Of two anchors of the same period, the one written first is the next one.');
        self::assertSame([$explained], $this->said(new ChainVerifier()->verify('orders', $restored, [$lost, new Anchor('orders', $restore->recordedAt, $rows[0]->hash, $restore->hash)])), "A discontinuity recorded at the end of the next anchor's period is covered by it.");
    }

    /**
     * Segments of many anchors, some lost, some of the same period, read in any order: each lost anchor is explained
     * by the same discontinuity as a pass over every anchor would find, and each discontinuity is covered alike.
     */
    public function testManyAnchorsAreJudgedAsAPassOverEveryAnchorWould(): void
    {
        $random = new Randomizer(new Mt19937(20_261_105));

        for ($round = 0; $round < 200; ++$round) {
            $rows = [];
            $previous = null;

            for ($sequence = 1, $count = $random->getInt(1, 12); $sequence <= $count; ++$sequence) {
                $kind = 1 === $random->getInt(1, 3) ? RowKind::Discontinuity : RowKind::Event;
                $previous = AuditRow::append($previous, 'orders', $kind, new DateTimeImmutable(\sprintf('2026-11-04 12:00 UTC +%d minutes', 10 * $sequence)), 'v1', '{}');
                $rows[] = $previous;
            }

            $anchors = [];

            for ($index = 0, $count = $random->getInt(0, 16); $index < $count; ++$index) {
                $head = 1 === $random->getInt(1, 3) ? str_repeat(dechex($random->getInt(10, 15)), 64) : $rows[$random->getInt(0, \count($rows) - 1)]->hash;
                $anchors[] = new Anchor('orders', new DateTimeImmutable(\sprintf('2026-11-04 12:00 UTC +%d minutes', 5 * $random->getInt(0, 30))), $rows[0]->hash, $head);
            }

            self::assertSame($this->judged($rows, $anchors), $this->said(new ChainVerifier()->verify('orders', $rows, $anchors)), 'Round '.$round);
        }
    }

    public function testTheRowsAreReadOnceAndInOrder(): void
    {
        [$rows, $anchors] = $this->segment();
        $stream = new ArrayIterator($rows);
        $read = (static function () use ($stream): iterable {
            yield from $stream;
        })();

        self::assertSame([], new ChainVerifier()->verify('orders', $read, [...$anchors, ...$anchors]));
        self::assertFalse($stream->valid(), 'Every row was read.');
        self::assertFalse($read->valid(), 'The stream is not rewound: it was read once.');
    }

    /**
     * @return array{list<AuditRow>, list<Anchor>}
     */
    private function segment(): array
    {
        $first = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00 UTC'), 'v1', '{"amount":10}');
        $second = AuditRow::append($first, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:10 UTC'), 'v1', '{"amount":20}');
        $third = AuditRow::append($second, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:20 UTC'), 'v1', '{"amount":30}');

        return [[$first, $second, $third], [new Anchor('orders', new DateTimeImmutable('2026-11-04 12:15 UTC'), $first->hash, $second->hash), new Anchor('orders', new DateTimeImmutable('2026-11-04 12:30 UTC'), $first->hash, $third->hash)]];
    }

    /**
     * What a pass over every anchor and every discontinuity says of an intact chain.
     *
     * @param list<AuditRow> $rows
     * @param list<Anchor>   $anchors
     *
     * @return list<string>
     */
    private function judged(array $rows, array $anchors): array
    {
        $found = [];

        foreach ($anchors as $anchor) {
            $found[$anchor->headHash] = null;

            foreach ($rows as $row) {
                if ($row->hash === $anchor->headHash) {
                    $found[$anchor->headHash] = $row->sequence;

                    break;
                }
            }
        }

        $said = [];

        foreach ($anchors as $lost) {
            if (null !== $found[$lost->headHash]) {
                continue;
            }

            $next = null;

            foreach ($anchors as $anchor) {
                if (null !== $found[$anchor->headHash] && $anchor->periodEnd > $lost->periodEnd && (null === $next || $anchor->periodEnd < $next->periodEnd)) {
                    $next = $anchor;
                }
            }

            $explaining = null;

            foreach ($rows as $row) {
                if (null !== $next && null === $explaining && RowKind::Discontinuity === $row->kind && $row->recordedAt > $lost->periodEnd && $row->recordedAt <= $next->periodEnd && $row->sequence <= $found[$next->headHash]) {
                    $explaining = $row;
                }
            }

            $at = $lost->periodEnd->format(\DATE_ATOM);
            $said[] = null === $explaining
                ? 'orders: no row has the head hash anchored at '.$at
                : \sprintf('orders #%d: no row has the head hash anchored at %s: explained by this discontinuity', $explaining->sequence, $at);
        }

        foreach ($rows as $row) {
            if ([] !== $anchors && RowKind::Discontinuity === $row->kind && !array_any($found, static fn (?int $sequence): bool => null !== $sequence && $sequence >= $row->sequence)) {
                $said[] = \sprintf('orders #%d: the discontinuity is covered by no anchor', $row->sequence);
            }
        }

        return $said;
    }

    /**
     * @param list<ChainBreak> $breaks
     *
     * @return list<string>
     */
    private function said(array $breaks): array
    {
        return array_map(static fn (ChainBreak $break): string => $break->toString(), $breaks);
    }
}
