<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Exception\NotOrganizer;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Application\View\AnswerSheet;
use Phil\SchedulingPollBundle\Application\View\ResponseRow;
use Phil\SchedulingPollBundle\Application\View\ResultsView;
use Phil\SchedulingPollBundle\Application\View\SlotLine;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchedulingPollViews::class)]
#[CoversClass(AnswerSheet::class)]
#[CoversClass(ResultsView::class)]
#[CoversClass(ResponseRow::class)]
#[CoversClass(SlotLine::class)]
final class SchedulingPollViewsTest extends TestCase
{
    public function testASheetShowsThePollTheOwnAnswersAndTheCountsOfAll(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll(location: 'The back room');
        [$first, $second, $third] = $test->slotIds($poll);
        $bob = $test->polls->join($poll->id, 'Bob');
        $carol = $test->polls->join($poll->id, 'Carol');
        $test->polls->join($poll->id, 'Dave');
        $test->polls->answer($bob->token, [$first => Availability::Yes, $second => Availability::IfNeedBe]);
        $test->polls->answer($carol->token, [$first => Availability::Yes, $third => Availability::Yes]);

        $views = new SchedulingPollViews($test->polls);

        $sheet = $this->sheet($views, $bob->token);

        self::assertSame($poll->id, $sheet->poll);
        self::assertSame('Game night', $sheet->title);
        self::assertSame('Bring dice.', $sheet->description);
        self::assertSame('The back room', $sheet->location);
        self::assertSame('Bob', $sheet->name);
        self::assertSame('America/Toronto', $sheet->timezone->getName());
        self::assertSame('2026-11-28 05:00', $sheet->deadline->format('Y-m-d H:i'));
        self::assertTrue($sheet->answered);
        self::assertTrue($sheet->editable);
        self::assertFalse($sheet->closed);
        self::assertNull($sheet->chosen);
        self::assertSame([Availability::Yes, Availability::IfNeedBe, Availability::No], array_map(static fn (SlotLine $line): ?Availability => $line->answer, $sheet->slots));
        self::assertSame([[2, 0, 0, 1], [0, 1, 1, 3], [1, 0, 1, 2]], array_map(static fn (SlotLine $line): array => [$line->yes, $line->ifNeedBe, $line->no, $line->place], $sheet->slots));
        self::assertSame([true, false, false], array_map(static fn (SlotLine $line): bool => $line->isBest(), $sheet->slots));
        self::assertSame('2026-12-01 19:00 EST', $sheet->slots[0]->startsAt->format('Y-m-d H:i T'));

        $results = $sheet->results;
        self::assertInstanceOf(ResultsView::class, $results);
        self::assertTrue($results->visible);
        self::assertSame(2, $results->answered);
        self::assertSame(['Bob', 'Carol'], array_map(static fn (ResponseRow $row): ?string => $row->name, $results->rows));
        self::assertSame([1, 2], array_map(static fn (ResponseRow $row): int => $row->number, $results->rows));
        self::assertSame([true, false], array_map(static fn (ResponseRow $row): bool => $row->own, $results->rows));
        self::assertSame(Availability::IfNeedBe, $results->rows[0]->answerTo($second));
        self::assertNull($results->rows[0]->answerTo('elsewhere'));
        self::assertSame(Availability::IfNeedBe, $results->slots[1]->answer);
    }

    public function testADateEndingAtMidnightStaysOnItsDay(): void
    {
        $line = static fn (string $start, string $end): SlotLine => new SlotLine('slot', new DateTimeImmutable($start), new DateTimeImmutable($end));

        self::assertFalse($line('2026-12-01 22:00', '2026-12-02 00:00')->spansDays());
        self::assertTrue($line('2026-12-01 22:00', '2026-12-02 00:00:01')->spansDays());
        self::assertFalse($line('2026-12-01 22:00', '2026-12-01 23:00')->isBest());
    }

    public function testAnUnusableLinkOpensNoSheet(): void
    {
        self::assertNull(new SchedulingPollViews(Fixtures::memory()->polls)->sheet('nope'));
    }

    public function testEachRespondentReadsTheDatesInTheirZoneAcrossTheChangesOfTheHour(): void
    {
        $test = Fixtures::memory();
        $test->clock->modify('2026-10-20 10:00:00 UTC');
        // Toronto leaves summer time on 1 November 2026, Paris on 25 October: the gap between them is six hours, then
        // five for a week, then six again.
        $poll = $test->poll(['2026-10-24 19:00', '2026-10-31 19:00', '2026-11-07 19:00'], deadline: '2026-10-22 10:00:00 UTC');
        $views = new SchedulingPollViews($test->polls);
        $bob = $test->polls->join($poll->id, 'Bob');

        $toronto = $this->sheet($views, $bob->token);
        self::assertSame(['2026-10-24 19:00 EDT', '2026-10-31 19:00 EDT', '2026-11-07 19:00 EST'], array_map(static fn (SlotLine $line): string => $line->startsAt->format('Y-m-d H:i T'), $toronto->slots));

        $test->polls->answer($bob->token, [], 'Europe/Paris');
        $paris = $this->sheet($views, $bob->token);
        self::assertSame('Europe/Paris', $paris->timezone->getName());
        self::assertSame(['2026-10-25 01:00 CEST', '2026-11-01 00:00 CET', '2026-11-08 01:00 CET'], array_map(static fn (SlotLine $line): string => $line->startsAt->format('Y-m-d H:i T'), $paris->slots));
        self::assertSame(['2026-10-25 02:00 CET', '2026-11-01 02:00 CET', '2026-11-08 03:00 CET'], array_map(static fn (SlotLine $line): string => $line->endsAt->format('Y-m-d H:i T'), $paris->slots));
        self::assertSame([false, false, false], array_map(static fn (SlotLine $line): bool => $line->spansDays(), $paris->slots));
        self::assertSame('2026-10-22 12:00', $paris->deadline->format('Y-m-d H:i'));

        $asked = $this->sheet($views, $bob->token, 'Asia/Tokyo');
        self::assertSame('2026-10-25 08:00 JST', $asked->slots[0]->startsAt->format('Y-m-d H:i T'));
        self::assertSame('Europe/Paris', $this->sheet($views, $bob->token, 'Mars/Olympus')->timezone->getName());
        self::assertSame('America/Toronto', $views->results($poll->id, Fixtures::organizer(), 'Mars/Olympus')->timezone->getName());
        self::assertSame('Asia/Tokyo', $views->results($poll->id, Fixtures::organizer(), 'Asia/Tokyo')->timezone->getName());
    }

    /**
     * @return iterable<string, array{ResultsVisibility, bool, bool, bool}>
     */
    public static function visibilities(): iterable
    {
        // Who sees: before answering, after answering, once closed.
        yield 'always' => [ResultsVisibility::Always, true, true, true];
        yield 'after voting' => [ResultsVisibility::AfterVoting, false, true, true];
        yield 'after closing' => [ResultsVisibility::AfterClosing, false, false, true];
        yield 'managers' => [ResultsVisibility::Managers, false, false, false];
    }

    #[DataProvider('visibilities')]
    public function testARespondentSeesTheAnswersAsThePollSays(ResultsVisibility $visibility, bool $before, bool $answered, bool $closed): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll(results: $visibility);
        [$first] = $test->slotIds($poll);
        $views = new SchedulingPollViews($test->polls);
        $bob = $test->polls->join($poll->id, 'Bob');

        self::assertSame($before, $this->sheet($views, $bob->token)->results instanceof ResultsView);
        self::assertSame($before ? 0 : null, $this->sheet($views, $bob->token)->slots[0]->yes);

        $test->polls->answer($bob->token, [$first => Availability::Yes]);
        self::assertSame($answered, $this->sheet($views, $bob->token)->results instanceof ResultsView);
        self::assertSame($answered ? 1 : null, $this->sheet($views, $bob->token)->slots[0]->yes);

        $test->polls->close($poll->id, Fixtures::organizer(), $first);
        $sheet = $this->sheet($views, $bob->token);
        self::assertSame($closed, $sheet->results instanceof ResultsView);
        self::assertTrue($sheet->closed);
        self::assertFalse($sheet->editable);
        self::assertSame($first, $sheet->chosen?->id);
        self::assertTrue($sheet->slots[0]->chosen);
        self::assertFalse($sheet->slots[1]->chosen);
    }

    public function testTheOrganizerSeesTheAnswersBeforeTheClosingOnlyWhenThePollSaysSo(): void
    {
        $test = Fixtures::memory();
        $open = $test->poll();
        $sealed = $test->poll(organizerSeesAnswers: false);
        $views = new SchedulingPollViews($test->polls);

        foreach ([$open, $sealed] as $poll) {
            [$first] = $test->slotIds($poll);
            $test->polls->answer($test->polls->join($poll->id, 'Bob')->token, [$first => Availability::Yes]);
        }

        $seen = $views->results($open->id, Fixtures::organizer());
        self::assertTrue($seen->visible);
        self::assertSame(1, $seen->slots[0]->yes);
        self::assertCount(1, $seen->rows);
        self::assertFalse($seen->rows[0]->own);
        self::assertNull($seen->slots[0]->answer);
        self::assertSame('Game night', $seen->title);
        self::assertSame($open->id, $seen->poll);
        self::assertFalse($seen->closed);
        self::assertNull($seen->chosen);
        self::assertSame('2026-11-28 05:00', $seen->deadline->format('Y-m-d H:i'));

        $hidden = $views->results($sealed->id, Fixtures::organizer());
        self::assertFalse($hidden->visible);
        self::assertSame(1, $hidden->answered);
        self::assertSame([], $hidden->rows);
        self::assertNull($hidden->slots[0]->yes);
        self::assertFalse($hidden->slots[0]->isBest());
        self::assertCount(3, $hidden->slots);
        self::assertNull($hidden->chosen);
        self::assertSame([false, false, false], array_map(static fn (SlotLine $line): bool => $line->chosen, $hidden->slots));

        [$first] = $test->slotIds($sealed);
        $test->polls->close($sealed->id, Fixtures::organizer(), $first);
        $revealed = $views->results($sealed->id, Fixtures::organizer());
        self::assertTrue($revealed->visible);
        self::assertTrue($revealed->closed);
        self::assertSame($first, $revealed->chosen?->id);
        self::assertCount(1, $revealed->rows);

        $this->expectExceptionObject(NotOrganizer::of($open->id));
        $views->results($open->id, ResourceReference::of('user', 'mallory'));
    }

    public function testRevokedRespondentsAndThoseWhoHaveNotAnsweredHaveNoRowAndAnErasedNameIsNull(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $test->polls->join($poll->id, 'Silent');
        $bob = $test->polls->join($poll->id, 'Bob');
        $carol = $test->polls->join($poll->id, 'Carol');
        $test->polls->answer($bob->token, [$first => Availability::Yes]);
        $test->polls->answer($carol->token, [$first => Availability::Yes]);
        $test->polls->revoke($poll->id, Fixtures::organizer(), (string) $test->polls->open($carol->token)?->respondent->id);
        $test->polls->close($poll->id, Fixtures::organizer(), $first);
        $test->clock->modify('+31 days');
        $test->polls->eraseExpired();

        $results = new SchedulingPollViews($test->polls)->results($poll->id, Fixtures::organizer());

        self::assertSame(1, $results->answered);
        self::assertCount(1, $results->rows);
        self::assertNull($results->rows[0]->name);
        self::assertSame(2, $results->rows[0]->number);
    }

    /**
     * @return iterable<string, array{?int, ?int, ?int, bool}>
     */
    public static function places(): iterable
    {
        yield 'first with a yes' => [1, 1, null, true];
        yield 'first with an if need be alone' => [1, null, 1, true];
        yield 'first with a yes and an if need be' => [1, 1, 1, true];
        yield 'first with an if need be and no yes' => [1, 0, 1, true];
        yield 'first with a yes and no if need be' => [1, 1, 0, true];
        yield 'first with nobody' => [1, 0, 0, false];
        yield 'first with no count' => [1, null, null, false];
        yield 'second with a yes' => [2, 3, 0, false];
        yield 'no place' => [null, 3, 0, false];
    }

    #[DataProvider('places')]
    public function testADateIsBestWhenFirstWithSomeoneAbleToCome(?int $place, ?int $yes, ?int $ifNeedBe, bool $best): void
    {
        $start = new DateTimeImmutable('2026-12-01 19:00 UTC');

        self::assertSame($best, new SlotLine('slot', $start, $start->modify('+1 hour'), null, $yes, $ifNeedBe, null, $place)->isBest());
    }

    public function testALineIsNotChosenNorARowOwnUnlessSaid(): void
    {
        $start = new DateTimeImmutable('2026-12-01 19:00 UTC');

        self::assertFalse(new SlotLine('slot', $start, $start->modify('+1 hour'))->chosen);
        self::assertFalse(new ResponseRow('respondent', 1, 'Bob', [])->own);
    }

    private function sheet(SchedulingPollViews $views, string $token, ?string $timezone = null): AnswerSheet
    {
        $sheet = $views->sheet($token, $timezone);
        self::assertInstanceOf(AnswerSheet::class, $sheet);

        return $sheet;
    }
}
