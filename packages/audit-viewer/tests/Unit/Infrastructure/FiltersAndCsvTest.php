<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditViewer\Infrastructure\Csv\AuditCsv;
use Phil\AuditViewer\Infrastructure\Http\AuditFilters;
use Phil\AuditViewer\Tests\Unit\Fixture\TwoLineStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

#[CoversClass(AuditFilters::class)]
#[CoversClass(AuditCsv::class)]
final class FiltersAndCsvTest extends TestCase
{
    public function testEveryFilterIsRead(): void
    {
        $filters = AuditFilters::read(new InputBag([
            'actor' => ' acct-7 ',
            'action' => 'workflow.order.',
            'prefix' => '1',
            'subject_type' => 'order',
            'subject_id' => '42',
            'segment' => 'orders',
            'tenant' => 'Shop A',
            'since' => '2026-11-08T05:01',
            'until' => '2026-11-08T06:00',
            'cursor' => '2026-11-08T10:01:00.000000Z~orders~2',
            'other' => 'ignored',
        ]), new DateTimeZone('America/Toronto'), 7);

        $query = $filters->query;
        self::assertNotNull($query);
        self::assertSame([], $filters->errors);
        self::assertSame(['acct-7', 'workflow.order.', true, 'order', '42', 'orders', 'Shop A', 7, Order::NewestFirst], [$query->actor, $query->action, $query->actionIsPrefix, $query->subjectType, $query->subjectId, $query->segment, $query->tenant, $query->limit, $query->order]);
        self::assertSame(['2026-11-08T10:01:00.000000Z', '2026-11-08T11:00:00.000000Z'], [$query->sinceInstant(), $query->untilInstant()]);
        self::assertSame(['2026-11-08T10:01:00.000000Z', 'orders', 2], [$query->after?->instant, $query->after?->segment, $query->after?->sequence]);
        self::assertSame(['actor' => 'acct-7', 'action' => 'workflow.order.', 'prefix' => '1', 'subject_type' => 'order', 'subject_id' => '42', 'segment' => 'orders', 'tenant' => 'Shop A', 'since' => '2026-11-08T05:01', 'until' => '2026-11-08T06:00'], $filters->parameters());
        self::assertSame($filters->values, $filters->parameters());
        self::assertSame(['filter_actor' => 'acct-7', 'filter_action' => 'workflow.order.', 'filter_prefix' => '1', 'filter_subject_type' => 'order', 'filter_subject_id' => '42', 'filter_segment' => 'orders', 'filter_tenant' => 'Shop A', 'filter_since' => '2026-11-08T05:01', 'filter_until' => '2026-11-08T06:00'], $filters->journal());
        self::assertSame(AuditFilters::FIELDS, array_keys($filters->values));
    }

    public function testNoFilterReadsTheWholeLog(): void
    {
        $filters = AuditFilters::read(new InputBag(['actor' => '  ']), new DateTimeZone('UTC'), 50);

        self::assertSame([[], [], []], [$filters->values, $filters->errors, $filters->journal()]);
        self::assertNotNull($filters->query);
        self::assertSame([null, null, null, null, null, null, null], [$filters->query->actor, $filters->query->action, $filters->query->subjectType, $filters->query->tenant, $filters->query->since, $filters->query->until, $filters->query->after]);
        self::assertFalse($filters->query->actionIsPrefix);

        $typed = AuditFilters::read(new InputBag(['subject_type' => 'order', 'action' => 'order.paid']), new DateTimeZone('UTC'), 50)->query;
        self::assertSame(['order', null, 'order.paid', false], [$typed?->subjectType, $typed?->subjectId, $typed?->action, $typed?->actionIsPrefix]);

        $since = AuditFilters::read(new InputBag(['since' => '2026-11-08T00:00']), new DateTimeZone('UTC'), 50)->query;
        self::assertSame(['2026-11-08T00:00:00.000000Z', null], [$since?->sinceInstant(), $since?->untilInstant()]);

        $until = AuditFilters::read(new InputBag(['until' => '2026-11-08T00:00']), new DateTimeZone('UTC'), 50)->query;
        self::assertSame([null, '2026-11-08T00:00:00.000000Z'], [$until?->sinceInstant(), $until?->untilInstant()]);
    }

    public function testWhatDoesNotReadIsSaid(): void
    {
        $filters = AuditFilters::read(new InputBag([
            'actor' => 'ada@example.org',
            'action' => 'Order',
            'subject_id' => '42',
            'segment' => 'Orders',
            'tenant' => "shop\u{7}",
            'since' => '2026-11-08 05:01',
            'until' => '2026-02-30T10:00',
            'cursor' => 'nonsense',
        ]), new DateTimeZone('UTC'), 50);

        self::assertNull($filters->query);
        self::assertSame(['actor', 'action', 'subject_id', 'segment', 'tenant', 'since', 'until', 'cursor'], $filters->errors);
        self::assertSame('ada@example.org', $filters->values['actor']);

        self::assertSame(['subject_type'], AuditFilters::read(new InputBag(['subject_type' => 'order', 'subject_id' => 'a b']), new DateTimeZone('UTC'), 50)->errors);
        self::assertSame(['until'], AuditFilters::read(new InputBag(['since' => '2026-11-08T10:00', 'until' => '2026-11-08T09:00']), new DateTimeZone('UTC'), 50)->errors);
        self::assertSame(['since'], AuditFilters::read(new InputBag(['since' => '2026-11-08T10:00:00']), new DateTimeZone('UTC'), 50)->errors);
        self::assertSame(['since'], AuditFilters::read(new InputBag(['since' => 'x2026-11-08T10:00']), new DateTimeZone('UTC'), 50)->errors);
        self::assertSame(['until'], AuditFilters::read(new InputBag(['until' => "2026-11-08T10\u{0}:00"]), new DateTimeZone('UTC'), 50)->errors, 'A null byte is refused, not thrown on.');
        self::assertSame(['until'], AuditFilters::read(new InputBag(['until' => "x\u{0}2026-11-08T10:00"]), new DateTimeZone('UTC'), 50)->errors, 'A null byte before the date.');
        self::assertSame(['until'], AuditFilters::read(new InputBag(['until' => "2026-11-08T10:00\u{0}x"]), new DateTimeZone('UTC'), 50)->errors, 'A null byte after it.');
        self::assertSame(['until'], AuditFilters::read(new InputBag(['until' => "2026-11-08\nT10:00"]), new DateTimeZone('UTC'), 50)->errors);
    }

    public function testRowsAreWrittenAsCsvWithoutFormulas(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00:00.25 UTC');
        $first = AuditRow::append(null, 'orders', RowKind::Event, $at, 'json-1', '{"note":"=SUM(A1)"}', Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'shop-a'));
        $second = AuditRow::append($first, 'orders', RowKind::Discontinuity, $at, 'json-1', '{}');
        $changed = AuditRow::restore('orders', 3, RowKind::Event, $at, 'json-1', '-1', $second->hash, str_repeat('0', 64));
        $stream = $this->memory();

        self::assertSame(2, AuditCsv::write($stream, [$first, $second, $changed], 2));
        $lines = explode("\r\n", $this->contents($stream));

        self::assertSame([implode(',', AuditCsv::HEADER), '2026-11-08T10:00:00.250000Z,orders,1,event,order.paid,acct-7,order,42,shop-a,json-1,"{""note"":""=SUM(A1)""}",'.AuditRow::GENESIS.','.$first->hash.',yes', '2026-11-08T10:00:00.250000Z,orders,2,discontinuity,,,,,,json-1,{},'.$first->hash.','.$second->hash.',yes', ''], $lines);

        $all = $this->memory();
        self::assertSame(3, AuditCsv::write($all, [$first, $second, $changed], 3));
        self::assertStringEndsWith("'-1,".$second->hash.','.str_repeat('0', 64).",no\r\n", $this->contents($all));

        $none = $this->memory();
        self::assertSame(0, AuditCsv::write($none, [], 5));
        self::assertSame(implode(',', AuditCsv::HEADER)."\r\n", $this->contents($none));

        $closed = fopen('php://memory', 'r');
        self::assertNotFalse($closed);
        self::assertSame(0, AuditCsv::write($closed, [$first, $second], 5), 'A stream that takes nothing gets nothing.');

        stream_wrapper_register('two-lines', TwoLineStream::class);

        try {
            $short = fopen('two-lines://export', 'w');
            self::assertNotFalse($short);
            stream_set_write_buffer($short, 0);
            self::assertSame(1, AuditCsv::write($short, [$first, $second, $changed], 5), 'The writing stops where the stream does.');
            self::assertCount(2, TwoLineStream::$taken);

            TwoLineStream::$refuse = 1;
            $refused = fopen('two-lines://export', 'w');
            self::assertNotFalse($refused);
            stream_set_write_buffer($refused, 0);
            self::assertSame(0, AuditCsv::write($refused, [$first, $second], 5), 'No row follows a header the stream refused.');
            self::assertSame([], TwoLineStream::$taken);
        } finally {
            TwoLineStream::$refuse = 0;
            stream_wrapper_unregister('two-lines');
        }

        foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\tx", "\rx", "\nx"] as $formula) {
            self::assertSame("'".$formula, AuditCsv::cell($formula));
        }

        foreach (['', 'acct-7', 'a=b', '1-2', ' =1'] as $plain) {
            self::assertSame($plain, AuditCsv::cell($plain));
        }
    }

    /**
     * @return resource
     */
    private function memory(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertNotFalse($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function contents(mixed $stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
