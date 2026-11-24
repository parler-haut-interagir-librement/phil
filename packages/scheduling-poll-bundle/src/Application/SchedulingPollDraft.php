<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use DateTimeImmutable;
use Phil\PollBundle\Domain\Status\ResultsVisibility;

/**
 * What a new scheduling poll is made of: its title, the dates it proposes, the time zone they are shown in, its
 * deadline, and who sees the answers. By default the organizer sees them as they arrive, and so do the respondents.
 */
final readonly class SchedulingPollDraft
{
    /**
     * @param list<ProposedSlot> $slots                two to a hundred, each starting at another moment
     * @param string             $timezone             the time zone of the IANA database the dates are shown in
     * @param DateTimeImmutable  $deadline             when the best date is chosen, if the organizer chose none before
     * @param bool               $organizerSeesAnswers whether the organizer sees the answers before the closing
     * @param ResultsVisibility  $results              who else sees them: everyone, a respondent once they answered,
     *                                                 everyone once closed, or the organizer only
     */
    public function __construct(
        public string $title,
        public array $slots,
        public string $timezone,
        public DateTimeImmutable $deadline,
        public ?string $description = null,
        public ?string $location = null,
        public bool $organizerSeesAnswers = true,
        public ResultsVisibility $results = ResultsVisibility::Always,
    ) {
    }
}
