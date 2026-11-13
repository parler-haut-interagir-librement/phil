<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\CountingMethods;
use Phil\Poll\Standing;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Application\Exception\NotFound;
use Phil\PollBundle\Application\View\BallotChoice;
use Phil\PollBundle\Application\View\BallotView;
use Phil\PollBundle\Application\View\ResultLine;
use Phil\PollBundle\Application\View\ResultsView;
use Phil\PollBundle\Domain\Counting\Billionths;
use Phil\PollBundle\Domain\Counting\Contract\ElectingMethod;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;

/**
 * What a page shows of a poll to one viewer — `null` for someone not signed in —: the form of its ballot, with what
 * the viewer may do, and its results when they are theirs to see. The bundles built on the polls and the application
 * draw them with `@PhilPoll/ballot.html.twig` and `@PhilPoll/results.html.twig`, or their own templates.
 *
 * The service keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class PollViews
{
    public function __construct(
        private Polls $polls,
        private PollPolicy $policy,
        private CountingMethods $methods,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The form of the poll's ballot for a viewer: the options with what the viewer's named ballot gave them — an
     * anonymous ballot is not known —, and whether they may cast a ballot, or change theirs.
     *
     * @throws NotFound
     */
    public function ballot(string $pollId, ?ResourceReference $viewer): BallotView
    {
        $poll = $this->polls->get($pollId);
        $rules = $poll->rules();
        $open = $poll->isOpenAt($this->clock->now());
        $voted = $viewer instanceof ResourceReference && $this->polls->hasVoted($poll->id, $viewer);
        $mayVote = $open && $viewer instanceof ResourceReference && $this->policy->mayVote($poll, $viewer);
        $ballot = $viewer instanceof ResourceReference ? $this->polls->ballotOf($poll->id, $viewer) : null;
        $chosen = $ballot instanceof Ballot ? array_flip($ballot->choices) : [];
        $ranked = BallotKind::Ranked === $rules->kind;
        $choices = [];

        foreach ($poll->options() as $option) {
            $position = $chosen[$option->id] ?? null;
            $choices[] = new BallotChoice($option->id, $option->label, null !== $position, $ranked && null !== $position ? $position + 1 : null);
        }

        return new BallotView(
            $poll->id,
            $poll->question(),
            $rules->kind->value,
            $choices,
            $rules->minChoices,
            $rules->maxChoices,
            $rules->abstention,
            $poll->isAnonymous(),
            $poll->seats(),
            $open,
            $voted,
            $mayVote && !$voted,
            $mayVote && $voted && !$poll->isAnonymous(),
        );
    }

    /**
     * The results of the poll for a viewer, `null` while they are not theirs to see. A score counted in billionths of
     * a vote, as a single transferable vote counts it, is written with two decimals, truncated.
     *
     * @throws NotFound
     */
    public function results(string $pollId, ?ResourceReference $viewer): ?ResultsView
    {
        $poll = $this->polls->get($pollId);

        if (!$this->polls->maySeeResults($poll, $viewer)) {
            return null;
        }

        $result = $this->polls->results($poll->id);
        $billionths = $this->methods->get($poll->counting()) instanceof ElectingMethod;
        $winners = $result->winners();
        $max = max(1, ...array_map(static fn (Standing $standing): int => $standing->score, $result->standings));
        $lines = [];

        foreach ($result->standings as $standing) {
            $lines[] = new ResultLine(
                $standing->option,
                $poll->label($standing->option) ?? $standing->option,
                $standing->place,
                $standing->score,
                $billionths ? $this->hundredths($standing->score) : (string) $standing->score,
                $max,
                \in_array($standing->option, $winners, true),
            );
        }

        return new ResultsView(
            $poll->id,
            $poll->question(),
            $result->method,
            $result->ballots,
            $result->abstentions,
            $poll->seats(),
            $lines,
            $poll->isClosedAt($this->clock->now()),
        );
    }

    private function hundredths(int $billionths): string
    {
        return \sprintf('%d.%02d', intdiv($billionths, Billionths::SCALE), intdiv($billionths % Billionths::SCALE, Billionths::SCALE / 100));
    }
}
