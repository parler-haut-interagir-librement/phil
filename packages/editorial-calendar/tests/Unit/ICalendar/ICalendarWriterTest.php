<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit\ICalendar;

use DateTimeImmutable;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\ICalendar\ICalendarWriter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ICalendarWriter::class)]
final class ICalendarWriterTest extends TestCase
{
    public function testEachItemAloneAndEachBatchIsAnEvent(): void
    {
        $item = static fn (string $id, string $title, ?string $at = null): PlannedItem => PlannedItem::plan($id, ResourceReference::of('article', $id), $title, 'fr', new AuthorId('u'), null === $at ? null : new DateTimeImmutable($at));
        $calendar = EditorialCalendar::of(
            [
                $item('a', 'Launch; part one, with a comma', '2026-11-16 10:00+01:00')->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready)->transitionTo(ItemState::Scheduled),
                $item('b', 'Two'),
                $item('c', 'Three'),
                $item('outside', 'Later', '2027-01-01Z'),
            ],
            [new PublicationBatch('wave', "Wave\\one\nnext", ['b', 'c'], new DateTimeImmutable('2026-11-17 12:00Z'))],
        );

        $written = new ICalendarWriter('example.org')->write($calendar, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable('2026-11-13 08:30+01:00'), 'Blog, FR');

        self::assertSame(
            "BEGIN:VCALENDAR\r\n"
            ."VERSION:2.0\r\n"
            ."PRODID:-//PHIL//Editorial calendar//EN\r\n"
            ."CALSCALE:GREGORIAN\r\n"
            ."X-WR-CALNAME:Blog\\, FR\r\n"
            ."BEGIN:VEVENT\r\n"
            ."UID:item-a@example.org\r\n"
            ."DTSTAMP:20261113T073000Z\r\n"
            ."DTSTART:20261116T090000Z\r\n"
            ."SUMMARY:Launch\\; part one\\, with a comma\r\n"
            ."DESCRIPTION:Launch\\; part one\\, with a comma (fr\\, scheduled\\, article:a)\r\n"
            ."CATEGORIES:scheduled\r\n"
            ."STATUS:CONFIRMED\r\n"
            ."TRANSP:TRANSPARENT\r\n"
            ."END:VEVENT\r\n"
            ."BEGIN:VEVENT\r\n"
            ."UID:batch-wave@example.org\r\n"
            ."DTSTAMP:20261113T073000Z\r\n"
            ."DTSTART:20261117T120000Z\r\n"
            ."SUMMARY:Wave\\\\one\\nnext (2)\r\n"
            ."DESCRIPTION:Two (fr\\, idea\\, article:b)\\nThree (fr\\, idea\\, article:c)\r\n"
            ."CATEGORIES:batch\r\n"
            ."STATUS:TENTATIVE\r\n"
            ."TRANSP:TRANSPARENT\r\n"
            ."END:VEVENT\r\n"
            ."END:VCALENDAR\r\n",
            $written,
        );
    }

    public function testTheDefaultNameAndAnEmptyCalendar(): void
    {
        $written = new ICalendarWriter('a.example')->write(EditorialCalendar::of(), new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());

        self::assertStringContainsString("X-WR-CALNAME:Editorial calendar\r\n", $written);
        self::assertStringNotContainsString('VEVENT', $written);
    }

    public function testAPublishedBatchIsConfirmedAndCarriageReturnsAreEscaped(): void
    {
        $item = PlannedItem::plan('a', ResourceReference::of('article', 'a'), "One\r\ntwo\rthree", 'fr', new AuthorId('u'))->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready);
        $calendar = EditorialCalendar::of([$item], [new PublicationBatch('w', 'W', ['a'], new DateTimeImmutable('2026-11-17Z'))])
            ->applyBatch('w', new DateTimeImmutable('2026-11-17Z'));

        $written = new ICalendarWriter('example.org')->write($calendar, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());

        self::assertStringContainsString("STATUS:CONFIRMED\r\n", $written);
        self::assertStringContainsString('DESCRIPTION:One\ntwo\nthree (fr\, published\, article:a)', $written);
    }

    public function testLongLinesAreFoldedAt75OctetsNeverInsideACharacter(): void
    {
        $title = str_repeat('é', 100);
        $calendar = EditorialCalendar::of([PlannedItem::plan('a', ResourceReference::of('article', 'a'), $title, 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-17Z'))]);

        $written = new ICalendarWriter('example.org')->write($calendar, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());
        $lines = explode("\r\n", $written);

        foreach ($lines as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
            self::assertTrue(mb_check_encoding($line, 'UTF-8'));
        }

        $summary = (string) preg_replace('/\r\n /', '', substr($written, (int) strpos($written, 'SUMMARY:'), 400));
        self::assertStringStartsWith('SUMMARY:'.$title."\r\n", $summary);

        $index = (int) array_search('SUMMARY:'.str_repeat('é', 33), $lines, true);
        self::assertGreaterThan(0, $index);
        self::assertSame(' '.str_repeat('é', 37), $lines[$index + 1]);
        self::assertSame(' '.str_repeat('é', 30), $lines[$index + 2]);
    }

    public function testALineOfExactly75OctetsIsNotFolded(): void
    {
        $title = str_repeat('a', 75 - \strlen('SUMMARY:'));
        $calendar = EditorialCalendar::of([PlannedItem::plan('a', ResourceReference::of('article', 'a'), $title, 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-17Z'))]);
        $written = new ICalendarWriter('example.org')->write($calendar, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());

        self::assertStringContainsString("\r\nSUMMARY:".$title."\r\nDESCRIPTION:", $written);

        $longer = EditorialCalendar::of([PlannedItem::plan('a', ResourceReference::of('article', 'a'), $title.str_repeat('b', 75), 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-17Z'))]);
        $folded = new ICalendarWriter('example.org')->write($longer, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());
        self::assertStringContainsString("\r\nSUMMARY:".$title."\r\n ".str_repeat('b', 74)."\r\n b\r\n", $folded);
    }

    public function testTheDomainIsAHostName(): void
    {
        foreach (['', 'exa mple.org', '-a.org', 'a-.org', 'a..org', str_repeat('a', 64).'.org', "a.org\n", 'a@b.org'] as $domain) {
            try {
                new ICalendarWriter($domain);
                self::fail('The domain "'.$domain.'" was accepted.');
            } catch (InvalidItem) {
                self::addToAssertionCount(1);
            }
        }

        self::assertStringContainsString('@localhost', new ICalendarWriter('localhost')->write(
            EditorialCalendar::of([PlannedItem::plan('a', ResourceReference::of('article', 'a'), 'T', 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-17Z'))]),
            new DateTimeImmutable('2026-11-01Z'),
            new DateTimeImmutable('2026-12-01Z'),
            new DateTimeImmutable(),
        ));
        new ICalendarWriter(str_repeat('a', 63).'.org');
        new ICalendarWriter('a-b.c1.org');
    }
}
