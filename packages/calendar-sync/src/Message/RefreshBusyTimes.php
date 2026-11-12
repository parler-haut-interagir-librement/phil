<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Message;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\SyncWindow;

/**
 * Asks for the busy periods of a connection to be read again, for a Messenger transport: the identifier of the
 * connection and the window, nothing else — no address, no token, no period read.
 */
final readonly class RefreshBusyTimes
{
    /**
     * @param string $from the start of the window, in UTC, as `DateTimeInterface::ATOM` writes it
     */
    public function __construct(
        public string $connectionId,
        public string $from,
        public int $days,
    ) {
    }

    public static function of(string $connectionId, DateTimeInterface $from, int $days): self
    {
        return new self($connectionId, DateTimeImmutable::createFromInterface($from)->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM), $days);
    }

    /**
     * @throws InvalidSync when the start is not written as `of()` writes it, or the days are out of range
     */
    public function window(): SyncWindow
    {
        $from = DateTimeImmutable::createFromFormat('!'.DateTimeInterface::ATOM, $this->from);

        if (!$from instanceof DateTimeImmutable || $from->format(DateTimeInterface::ATOM) !== $this->from) {
            throw InvalidSync::because('The start of a window is written as DateTimeInterface::ATOM writes it.');
        }

        return SyncWindow::days($from, $this->days);
    }
}
