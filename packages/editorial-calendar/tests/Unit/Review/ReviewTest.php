<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit\Review;

use DateTimeImmutable;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
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

#[CoversClass(Review::class)]
#[CoversClass(ReviewRules::class)]
#[CoversClass(Issue::class)]
#[CoversClass(IssueKind::class)]
final class ReviewTest extends TestCase
{
    public function testTheSameContentInOneLanguagePlannedTwiceIsReported(): void
    {
        $calendar = EditorialCalendar::of([
            $this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z'),
            $this->ready('b', 'article:1', 'en', '2026-11-16 11:00Z'),
            $this->ready('c', 'article:1', 'fr', '2026-11-18 10:00Z'),
            $this->ready('d', 'article:1', 'fr', '2026-11-17 10:00Z'),
            $this->ready('e', 'article:2', 'fr', '2026-11-17 10:00Z'),
            $this->ready('outside', 'article:1', 'fr', '2026-12-17 10:00Z'),
        ]);

        self::assertEquals(
            [new Issue(IssueKind::DuplicateContent, new DateTimeImmutable('2026-11-16 10:00Z'), new DateTimeImmutable('2026-11-18 10:00Z'), ['a', 'd', 'c'])],
            $this->issues($calendar),
        );
    }

    public function testAnItemOfABatchCountsAtTheMomentOfItsBatch(): void
    {
        $calendar = EditorialCalendar::of(
            [$this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z'), $this->ready('b', 'article:1', 'fr')],
            [new PublicationBatch('w', 'W', ['b'], new DateTimeImmutable('2026-11-20Z'))],
        );

        self::assertEquals(
            [new Issue(IssueKind::DuplicateContent, new DateTimeImmutable('2026-11-16 10:00Z'), new DateTimeImmutable('2026-11-20Z'), ['a', 'b'])],
            $this->issues($calendar),
        );
    }

    public function testAWithdrawnItemIsNoDuplicate(): void
    {
        $calendar = EditorialCalendar::of([
            $this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z'),
            $this->ready('b', 'article:1', 'fr', '2026-11-17 10:00Z')->transitionTo(ItemState::Withdrawn),
        ]);

        self::assertSame([], $this->issues($calendar));
    }

    public function testIdeasAndDraftsPlannedForAMomentAreNotReady(): void
    {
        $calendar = EditorialCalendar::of([
            $this->item('idea', '2026-11-16 10:00Z'),
            $this->item('draft', '2026-11-17 10:00Z')->transitionTo(ItemState::Drafting),
            $this->ready('ready', 'article:3', 'fr', '2026-11-18 10:00Z'),
            $this->ready('scheduled', 'article:4', 'fr', '2026-11-18 11:00Z')->transitionTo(ItemState::Scheduled),
            $this->item('unplanned'),
        ]);

        self::assertEquals([
            new Issue(IssueKind::NotReady, new DateTimeImmutable('2026-11-16 10:00Z'), new DateTimeImmutable('2026-11-16 10:00Z'), ['idea']),
            new Issue(IssueKind::NotReady, new DateTimeImmutable('2026-11-17 10:00Z'), new DateTimeImmutable('2026-11-17 10:00Z'), ['draft']),
        ], $this->issues($calendar));
    }

    public function testPublicationsCloserThanTheSpacingAreReported(): void
    {
        $calendar = EditorialCalendar::of(
            [
                $this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z'),
                $this->ready('b', 'article:2', 'fr', '2026-11-16 11:59:59Z'),
                $this->ready('c', 'article:3', 'fr', '2026-11-16 13:59:59Z'),
                $this->ready('d', 'article:4', 'fr'),
                $this->ready('e', 'article:5', 'fr'),
            ],
            [new PublicationBatch('w', 'W', ['d', 'e'], new DateTimeImmutable('2026-11-16 15:00Z'))],
        );

        self::assertEquals([
            new Issue(IssueKind::TooClose, new DateTimeImmutable('2026-11-16 10:00Z'), new DateTimeImmutable('2026-11-16 11:59:59Z'), ['a', 'b']),
            new Issue(IssueKind::TooClose, new DateTimeImmutable('2026-11-16 13:59:59Z'), new DateTimeImmutable('2026-11-16 15:00Z'), ['c', 'd', 'e']),
        ], $this->issues($calendar, new ReviewRules(minimumSpacing: 7200)));
        self::assertSame([], $this->issues($calendar));
        self::assertCount(1, $this->issues($calendar, new ReviewRules(minimumSpacing: 7199)));
    }

    public function testTransitionsPlannedElsewhereAreNoPublications(): void
    {
        self::assertSame([], $this->issues(EditorialCalendar::of([$this->ready('a', 'article:1', 'fr', '2026-11-16 10:00Z')]), new ReviewRules(minimumSpacing: 86_400)));
    }

    public function testStretchesWithoutPublicationAreGaps(): void
    {
        $calendar = EditorialCalendar::of([
            $this->ready('a', 'article:1', 'fr', '2026-11-17Z'),
            $this->ready('b', 'article:2', 'fr', '2026-11-20Z'),
        ]);

        self::assertEquals([
            new Issue(IssueKind::Gap, new DateTimeImmutable('2026-11-17Z'), new DateTimeImmutable('2026-11-20Z')),
            new Issue(IssueKind::Gap, new DateTimeImmutable('2026-11-20Z'), new DateTimeImmutable('2026-11-23Z')),
        ], $this->issues($calendar, new ReviewRules(maximumGap: 86_400)));
        self::assertEquals(
            [new Issue(IssueKind::Gap, new DateTimeImmutable('2026-11-17Z'), new DateTimeImmutable('2026-11-20Z')), new Issue(IssueKind::Gap, new DateTimeImmutable('2026-11-20Z'), new DateTimeImmutable('2026-11-23Z'))],
            $this->issues($calendar, new ReviewRules(maximumGap: 259_199)),
        );
        self::assertSame([], $this->issues($calendar, new ReviewRules(maximumGap: 259_200)));
        self::assertEquals(
            [new Issue(IssueKind::Gap, new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-23Z'))],
            $this->issues(EditorialCalendar::of(), new ReviewRules(maximumGap: 1)),
        );
    }

    public function testTheGapsAreMeasuredFromTheBoundsInUtc(): void
    {
        $calendar = EditorialCalendar::of([$this->ready('a', 'article:1', 'fr', '2026-11-16 00:30Z')]);
        $issues = new Review(new ReviewRules(maximumGap: 3_600))->issues($calendar, new DateTimeImmutable('2026-11-16 01:00+01:00'), new DateTimeImmutable('2026-11-16 03:00+01:00'));

        self::assertCount(1, $issues);
        self::assertSame('2026-11-16T00:30:00+00:00', $issues[0]->from->format('c'));
        self::assertSame('2026-11-16T02:00:00+00:00', $issues[0]->to->format('c'));
        self::assertSame('UTC', $issues[0]->to->getTimezone()->getName());
    }

    public function testTheKindsComeInTheirOrder(): void
    {
        $calendar = EditorialCalendar::of([
            $this->item('a', '2026-11-16 10:00Z'),
            PlannedItem::plan('b', ResourceReference::of('article', 'a'), 'T', 'fr', new AuthorId('u'), new DateTimeImmutable('2026-11-16 10:30Z')),
        ]);

        self::assertSame(
            [IssueKind::DuplicateContent, IssueKind::NotReady, IssueKind::NotReady, IssueKind::TooClose, IssueKind::Gap],
            array_map(static fn (Issue $issue): IssueKind => $issue->kind, $this->issues($calendar, new ReviewRules(3_600, 86_400))),
        );
    }

    public function testTheRulesAreChecked(): void
    {
        self::assertSame(0, new ReviewRules()->minimumSpacing);
        self::assertNull(new ReviewRules()->maximumGap);
        self::assertSame(1, new ReviewRules(0, 1)->maximumGap);

        foreach ([[-1, null], [0, 0]] as [$spacing, $gap]) {
            try {
                new ReviewRules($spacing, $gap);
                self::fail('Wrong rules were accepted.');
            } catch (InvalidItem) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRangeEndsAfterItStarts(): void
    {
        $this->expectException(InvalidItem::class);
        new Review()->issues(EditorialCalendar::of(), new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-15Z'));
    }

    /**
     * @return list<Issue>
     */
    private function issues(EditorialCalendar $calendar, ReviewRules $rules = new ReviewRules()): array
    {
        return new Review($rules)->issues($calendar, new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-23Z'));
    }

    private function item(string $id, ?string $at = null): PlannedItem
    {
        return PlannedItem::plan($id, ResourceReference::of('article', $id), 'T', 'fr', new AuthorId('u'), null === $at ? null : new DateTimeImmutable($at));
    }

    private function ready(string $id, string $content, string $locale, ?string $at = null): PlannedItem
    {
        return PlannedItem::plan($id, ResourceReference::fromString($content), 'T', $locale, new AuthorId('u'), null === $at ? null : new DateTimeImmutable($at))
            ->transitionTo(ItemState::Drafting)
            ->transitionTo(ItemState::Ready);
    }
}
