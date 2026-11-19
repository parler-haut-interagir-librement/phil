<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Internal\Utc;

/**
 * A phase and the instants it opens and closes, kept in UTC: open from its start, included, to its end, excluded.
 */
final readonly class PhaseWindow
{
    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    /**
     * @throws InvalidInput when the phase is not one a schedule dates, or the window ends before it starts
     */
    public function __construct(
        public Phase $phase,
        DateTimeInterface $start,
        DateTimeInterface $end,
    ) {
        if (Phase::NotStarted === $phase || Phase::Closed === $phase) {
            throw InvalidInput::because('A schedule dates the submission, review, author response and deliberation phases only.');
        }

        $this->start = Utc::of($start);
        $this->end = Utc::of($end);

        if ($this->end <= $this->start) {
            throw InvalidInput::because(\sprintf('The %s phase ends after it starts.', $phase->value));
        }
    }

    public function contains(DateTimeInterface $instant): bool
    {
        return $instant >= $this->start && $instant < $this->end;
    }
}
