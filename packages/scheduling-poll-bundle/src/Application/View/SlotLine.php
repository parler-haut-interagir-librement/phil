<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\View;

use DateTimeImmutable;
use Phil\SchedulingPollBundle\Domain\Availability;

/**
 * One proposed date as a page shows it: its start and its end in the time zone of the viewer, the viewer's own answer,
 * and, when the answers are the viewer's to see, what the date gathered and its place.
 */
final readonly class SlotLine
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?Availability $answer = null,
        public ?int $yes = null,
        public ?int $ifNeedBe = null,
        public ?int $no = null,
        public ?int $place = null,
        public bool $chosen = false,
    ) {
    }

    /**
     * Whether the date is among the best so far: first place, with someone able to come.
     */
    public function isBest(): bool
    {
        return 1 === $this->place && ($this->yes ?? 0) + ($this->ifNeedBe ?? 0) > 0;
    }

    /**
     * Whether the date spans more than one day in the viewer's time zone, for a page that writes both days.
     */
    public function spansDays(): bool
    {
        return $this->startsAt->format('Y-m-d') !== $this->endsAt->modify('-1 second')->format('Y-m-d');
    }
}
