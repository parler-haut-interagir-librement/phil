<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Calendar;

use DateTimeImmutable;
use Override;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;

/**
 * The confirmed reservations as items of `phil/calendar-bundle`'s views: titled and tagged with their resource's name,
 * so that the tag columns lay the resources side by side. The holder is never shown.
 */
final readonly class ReservationItems implements ItemSource
{
    public const string SOURCE = 'reservation';

    public function __construct(
        private ReservationRepository $reservations,
        private int $limit = 1000,
    ) {
    }

    #[Override]
    public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $zone = $from->getTimezone();
        $items = [];

        foreach ($this->reservations->between($from, $until, $this->limit) as $reservation) {
            if (ReservationStatus::Confirmed !== $reservation->status()) {
                continue;
            }

            $interval = $reservation->interval();
            $name = $reservation->resource()->name();
            $items[] = new Item(
                self::SOURCE.':'.$reservation->id,
                $name,
                $interval->start->setTimezone($zone),
                $interval->end->setTimezone($zone),
                false,
                self::SOURCE,
                tags: [$name],
            );
        }

        return $items;
    }
}
