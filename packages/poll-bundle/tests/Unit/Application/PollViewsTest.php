<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Application\PollViews;
use Phil\PollBundle\Application\View\BallotChoice;
use Phil\PollBundle\Application\View\BallotView;
use Phil\PollBundle\Application\View\ResultLine;
use Phil\PollBundle\Application\View\ResultsView;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PollViews::class)]
#[CoversClass(BallotView::class)]
#[CoversClass(BallotChoice::class)]
#[CoversClass(ResultsView::class)]
#[CoversClass(ResultLine::class)]
final class PollViewsTest extends TestCase
{
    private MockClock $clock;

    private Polls $polls;

    private PollViews $views;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $policy = new Policy(refusedVoters: ['user:guest']);
        $methods = new CountingMethods([new ChoiceCount(), new InstantRunoff(), new MeekStv()]);
        $this->polls = new Polls(new InMemoryPollRepository(), new InMemoryVoteRepository(), $this->clock, $policy, $methods);
        $this->views = new PollViews($this->polls, $policy, $methods, $this->clock);
    }

    public function testTheBallotOfSomeoneWhoHasNotVoted(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee'), abstention: true), $this->ref('user:ada'));
        $view = $this->views->ballot($poll->id, $this->ref('user:bob'));

        self::assertEquals(new BallotView($poll->id, 'Tea or coffee?', 'single', [new BallotChoice('tea', 'TEA'), new BallotChoice('coffee', 'COFFEE')], 1, 1, true, false, 1, true, false, true, false), $view);
        self::assertTrue($view->isEditable());
    }

    public function testTheBallotOfANamedVoterShowsTheirChoicesAndMayBeChanged(): void
    {
        $poll = $this->polls->create(new PollDraft('Order?', $this->options('a', 'b', 'c'), BallotKind::Ranked, maxChoices: 2), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['c', 'a']);
        $view = $this->views->ballot($poll->id, $this->ref('user:bob'));

        self::assertSame('ranked', $view->kind);
        self::assertSame([1, 2], [$view->minChoices, $view->maxChoices]);
        self::assertEquals([new BallotChoice('a', 'A', true, 2), new BallotChoice('b', 'B'), new BallotChoice('c', 'C', true, 1)], $view->choices);
        self::assertSame([true, false, true, true], [$view->voted, $view->mayCast, $view->mayChange, $view->isEditable()]);
    }

    public function testAMultipleBallotShowsTheChoicesWithoutRanks(): void
    {
        $poll = $this->polls->create(new PollDraft('Which?', $this->options('a', 'b', 'c'), BallotKind::Multiple), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['c', 'a']);

        self::assertEquals([new BallotChoice('a', 'A', true), new BallotChoice('b', 'B'), new BallotChoice('c', 'C', true)], $this->views->ballot($poll->id, $this->ref('user:bob'))->choices);
    }

    public function testAnAnonymousBallotIsNeitherShownNorChanged(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee'), anonymous: true), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['tea']);
        $view = $this->views->ballot($poll->id, $this->ref('user:bob'));

        self::assertEquals([new BallotChoice('tea', 'TEA'), new BallotChoice('coffee', 'COFFEE')], $view->choices);
        self::assertSame([true, true, false, false, false], [$view->anonymous, $view->voted, $view->mayCast, $view->mayChange, $view->isEditable()]);
    }

    public function testNobodyVotesWhenSignedOutRefusedOrOutsideTheDates(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee'), closesAt: new DateTimeImmutable('2026-11-09 10:00:00 UTC')), $this->ref('user:ada'));

        foreach ([null, $this->ref('user:guest')] as $viewer) {
            $view = $this->views->ballot($poll->id, $viewer);
            self::assertSame([true, false, false, false], [$view->open, $view->voted, $view->mayCast, $view->mayChange]);
        }

        $this->polls->cast($poll->id, $this->ref('user:bob'), ['tea']);
        $this->clock->modify('+1 day');
        $view = $this->views->ballot($poll->id, $this->ref('user:bob'));

        self::assertSame([false, true, false, false], [$view->open, $view->voted, $view->mayCast, $view->mayChange]);
        self::assertSame([false, false], [$this->views->ballot($poll->id, $this->ref('user:cid'))->mayCast, $this->views->ballot($poll->id, $this->ref('user:cid'))->voted]);
    }

    public function testTheResultsAreShownWithTheirBars(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee', 'cocoa'), abstention: true), $this->ref('user:ada'));

        foreach (['bob' => ['tea'], 'cid' => ['tea'], 'dan' => ['coffee'], 'eve' => []] as $voter => $choices) {
            $this->polls->cast($poll->id, $this->ref('user:'.$voter), $choices);
        }

        $view = $this->views->results($poll->id, null);

        self::assertEquals(new ResultsView($poll->id, 'Tea or coffee?', 'choices', 4, 1, 1, [
            new ResultLine('tea', 'TEA', 1, 2, '2', 2, true),
            new ResultLine('coffee', 'COFFEE', 2, 1, '1', 2, false),
            new ResultLine('cocoa', 'COCOA', 3, 0, '0', 2, false),
        ], false), $view);
        self::assertSame(['TEA'], $view?->winners());
    }

    public function testTheWinnersAreListedWhereverTheirLines(): void
    {
        $view = new ResultsView('p', 'Q', 'choices', 3, 0, 1, [
            new ResultLine('a', 'A', 2, 1, '1', 2, false),
            new ResultLine('b', 'B', 1, 2, '2', 2, true),
            new ResultLine('c', 'C', 1, 2, '2', 2, true),
        ], true);

        self::assertSame(['B', 'C'], $view->winners());
    }

    public function testResultsWithoutBallotsHaveBarsOfOne(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee')), $this->ref('user:ada'));
        $view = $this->views->results($poll->id, null);

        self::assertSame([[1, false], [1, false]], array_map(static fn (ResultLine $line): array => [$line->max, $line->winner], $view->lines ?? []));
        self::assertSame([], $view?->winners());
    }

    public function testTheResultsAreHiddenUntilTheyAreTheViewersAndRevealedOnceClosed(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee'), results: ResultsVisibility::AfterClosing), $this->ref('user:ada'));

        self::assertNull($this->views->results($poll->id, $this->ref('user:bob')));
        self::assertFalse($this->views->results($poll->id, $this->ref('user:boss'))?->revealed);

        $this->polls->close($poll->id, $this->ref('user:boss'));

        self::assertTrue($this->views->results($poll->id, $this->ref('user:bob'))?->revealed);
    }

    public function testAnElectionShowsItsVotesWithTwoDecimals(): void
    {
        $poll = $this->polls->create(new PollDraft('Two seats', $this->options('a', 'b', 'c'), BallotKind::Ranked, counting: MeekStv::NAME, seats: 2), $this->ref('user:ada'));

        foreach (['u1' => ['a', 'b'], 'u2' => ['a', 'b'], 'u3' => ['a', 'c'], 'u4' => ['b'], 'u5' => ['c'], 'u6' => ['c', 'b']] as $voter => $choices) {
            $this->polls->cast($poll->id, $this->ref('user:'.$voter), $choices);
        }

        $view = $this->views->results($poll->id, null);
        $election = $this->polls->election($poll->id);

        self::assertSame(2, $view?->seats);
        self::assertSame($election->elected, array_values(array_map(static fn (ResultLine $line): string => $line->option, array_filter($view->lines, static fn (ResultLine $line): bool => $line->winner))));

        foreach ($view->lines as $line) {
            self::assertMatchesRegularExpression('/^\d+\.\d{2}$/D', $line->display);
            self::assertSame(\sprintf('%d.%02d', intdiv($line->score, 1_000_000_000), intdiv($line->score % 1_000_000_000, 10_000_000)), $line->display);
        }
    }

    public function testAScoreOfBillionthsIsTruncatedToHundredths(): void
    {
        $poll = $this->polls->create(new PollDraft('One seat', $this->options('a', 'b', 'c'), BallotKind::Ranked, counting: MeekStv::NAME), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:u1'), ['a']);
        $this->polls->cast($poll->id, $this->ref('user:u2'), ['b']);
        $this->polls->cast($poll->id, $this->ref('user:u3'), ['a']);

        $displays = array_map(static fn (ResultLine $line): string => $line->display, $this->views->results($poll->id, null)->lines ?? []);

        self::assertSame(['2.00', '1.00', '0.00'], $displays);
    }

    /**
     * @return list<PollOption>
     */
    private function options(string ...$ids): array
    {
        return array_values(array_map(static fn (string $id): PollOption => PollOption::of($id, strtoupper($id)), $ids));
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
