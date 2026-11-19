<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Fixture;

use Override;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The stock moves, reminders and events the services make, written down in order.
 */
final class Recorders implements StockLink, ReminderSender, EventDispatcherInterface
{
    /** @var list<string> */
    public array $lines = [];

    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function out(string $item, string $loan): void
    {
        $this->lines[] = 'out '.$item.' '.$loan;
    }

    #[Override]
    public function back(string $item, string $loan): void
    {
        $this->lines[] = 'back '.$item.' '.$loan;
    }

    #[Override]
    public function lost(string $item, string $loan): void
    {
        $this->lines[] = 'lost '.$item.' '.$loan;
    }

    #[Override]
    public function beforeDue(string $loan, int $offset): void
    {
        $this->lines[] = 'due '.$loan.' '.$offset;
    }

    #[Override]
    public function overdue(string $loan, int $notice): void
    {
        $this->lines[] = 'overdue '.$loan.' '.$notice;
    }

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
