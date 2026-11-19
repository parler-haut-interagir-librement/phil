<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use DateTimeInterface;
use Phil\ProposalReview\Exception\InvalidInput;

/**
 * The dated phases of a campaign, in their order, each starting where the one before ends. The author response phase
 * is optional, and so is the submission phase of a campaign whose proposals come from elsewhere; review and
 * deliberation are always there.
 */
final readonly class Schedule
{
    /** @var non-empty-list<PhaseWindow> */
    public array $windows;

    /**
     * @param list<PhaseWindow> $windows
     *
     * @throws InvalidInput
     */
    public function __construct(array $windows)
    {
        $phases = array_map(static fn (PhaseWindow $window): Phase => $window->phase, $windows);

        if (!\in_array(Phase::Review, $phases, true) || !\in_array(Phase::Deliberation, $phases, true)) {
            throw InvalidInput::because('A schedule has a review phase and a deliberation phase.');
        }

        $previous = null;

        foreach ($windows as $window) {
            if (null !== $previous && $window->phase->position() <= $previous->phase->position()) {
                throw InvalidInput::because('The phases of a schedule come once each, in their order.');
            }

            if (null !== $previous && $window->start->format('U.u') !== $previous->end->format('U.u')) {
                throw InvalidInput::because(\sprintf('The %s phase starts when the %s phase ends.', $window->phase->value, $previous->phase->value));
            }

            $previous = $window;
        }

        /** @var non-empty-list<PhaseWindow> $windows */
        $this->windows = $windows;
    }

    /**
     * The phase at an instant: the window that holds it, before the first one `NotStarted`, after the last one `Closed`.
     */
    public function phaseAt(DateTimeInterface $instant): Phase
    {
        if ($instant < $this->windows[0]->start) {
            return Phase::NotStarted;
        }

        foreach ($this->windows as $window) {
            if ($window->contains($instant)) {
                return $window->phase;
            }
        }

        return Phase::Closed;
    }

    public function has(Phase $phase): bool
    {
        return array_any($this->windows, static fn (PhaseWindow $window): bool => $window->phase === $phase);
    }
}
