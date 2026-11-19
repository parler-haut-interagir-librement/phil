<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit;

use DateTimeImmutable;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Exception\UnknownItem;
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ICalendar\ICalendarWriter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\Review\Issue;
use Phil\EditorialCalendar\Review\IssueKind;
use Phil\EditorialCalendar\Review\Review;
use Phil\EditorialCalendar\Review\ReviewRules;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges that the other tests leave open: what a batch may hold, how a line is folded, how issues are keyed.
 */
#[CoversClass(EditorialCalendar::class)]
#[CoversClass(Filter::class)]
#[CoversClass(ICalendarWriter::class)]
#[CoversClass(PublicationBatch::class)]
#[CoversClass(Review::class)]
final class SurvivorTest extends TestCase
{
    public function testABatchAddedLaterHoldsOnlyItemsOfTheCalendar(): void
    {
        $calendar = EditorialCalendar::of([$this->item('a', 'article:a', 'fr')]);

        $this->expectException(UnknownItem::class);
        $calendar->withBatch(new PublicationBatch('w', 'W', ['a', 'z']));
    }

    public function testStatesGivenByNameAreStillAccepted(): void
    {
        $filter = Filter::all()->byStates(...['first' => ItemState::Drafting]);

        self::assertTrue($filter->accepts($this->item('a', 'article:a', 'fr')->transitionTo(ItemState::Drafting)));
        self::assertFalse($filter->accepts($this->item('a', 'article:a', 'fr')));
    }

    public function testABatchIdentifierEndsWithItsLastCharacter(): void
    {
        $this->expectException(InvalidItem::class);
        new PublicationBatch("wave\n", 'W', ['a']);
    }

    public function testALineOf76OctetsIsFoldedAndOneOf149OctetsIsNot(): void
    {
        $prefix = \strlen('SUMMARY:');

        $folded = $this->written(str_repeat('a', 76 - $prefix));
        self::assertStringContainsString("\r\nSUMMARY:".str_repeat('a', 75 - $prefix)."\r\n a\r\nDESCRIPTION:", $folded);

        $whole = $this->written(str_repeat('a', 149 - $prefix));
        self::assertStringContainsString("\r\nSUMMARY:".str_repeat('a', 75 - $prefix)."\r\n ".str_repeat('a', 74)."\r\nDESCRIPTION:", $whole);
    }

    public function testContentAndLanguageAreNotRunTogether(): void
    {
        $calendar = EditorialCalendar::of([
            $this->ready('a', 'article:1', 'frb', '2026-11-16 10:00Z'),
            $this->ready('b', 'article:1f', 'rb', '2026-11-17 10:00Z'),
        ]);

        self::assertSame([], $this->issues($calendar));
    }

    public function testEveryRepeatedContentIsReported(): void
    {
        $calendar = EditorialCalendar::of([
            $this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z'),
            $this->ready('b', 'article:1', 'fr', '2026-11-17 10:00Z'),
            $this->ready('c', 'article:2', 'fr', '2026-11-18 10:00Z'),
            $this->ready('d', 'article:2', 'fr', '2026-11-19 10:00Z'),
        ]);

        self::assertSame([IssueKind::DuplicateContent, IssueKind::DuplicateContent], array_map(static fn (Issue $issue): IssueKind => $issue->kind, $this->issues($calendar)));
    }

    public function testAllTheItemsOfABatchAreNamedWhenItIsTooCloseToTheNext(): void
    {
        $calendar = EditorialCalendar::of(
            [
                $this->ready('a', 'article:1', 'fr'),
                $this->ready('b', 'article:2', 'fr'),
                $this->ready('c', 'article:3', 'fr', '2026-11-16 10:30Z'),
            ],
            [new PublicationBatch('w', 'W', ['a', 'b'], new DateTimeImmutable('2026-11-16 10:00Z'))],
        );

        $issues = $this->issues($calendar, new ReviewRules(minimumSpacing: 3_600));

        self::assertCount(1, $issues);
        self::assertSame(['a', 'b', 'c'], $issues[0]->items);
    }

    private function written(string $title): string
    {
        $calendar = EditorialCalendar::of([PlannedItem::plan('a', ResourceReference::of('article', 'a'), $title, 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-17Z'))]);

        return new ICalendarWriter('example.org')->write($calendar, new DateTimeImmutable('2026-11-01Z'), new DateTimeImmutable('2026-12-01Z'), new DateTimeImmutable());
    }

    /**
     * @return list<Issue>
     */
    private function issues(EditorialCalendar $calendar, ReviewRules $rules = new ReviewRules()): array
    {
        return new Review($rules)->issues($calendar, new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-23Z'));
    }

    private function item(string $id, string $content, string $locale, ?string $at = null): PlannedItem
    {
        return PlannedItem::plan($id, ResourceReference::fromString($content), 'T', $locale, new AuthorId('u'), null === $at ? null : new DateTimeImmutable($at));
    }

    private function ready(string $id, string $content, string $locale, ?string $at = null): PlannedItem
    {
        return $this->item($id, $content, $locale, $at)->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready);
    }
}
