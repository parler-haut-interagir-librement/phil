<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use DateInterval;
use DateTimeImmutable;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Throwable;

/**
 * When the reminders of an invitation fall due, counted from its creation: ISO 8601 durations in increasing order, up
 * to ten — `P3D`, `P7D` sends a reminder three days, then seven days after the invitation. A reminder that would fall
 * at the closure or after is not sent.
 */
final readonly class ReminderSchedule
{
    public const int MAX_REMINDERS = 10;

    /** @var list<DateInterval> */
    private array $delays;

    /**
     * @param list<string> $delays ISO 8601 durations, each later than the one before
     *
     * @throws InvalidConfiguration when a duration cannot be read, is not positive, is out of order, or more than ten are given
     */
    public function __construct(array $delays = [])
    {
        if (\count($delays) > self::MAX_REMINDERS) {
            throw InvalidConfiguration::because(\sprintf('An invitation has %d reminders at the most.', self::MAX_REMINDERS));
        }

        $reference = new DateTimeImmutable('2000-01-01 00:00:00 UTC');
        $previous = $reference;
        $intervals = [];

        foreach ($delays as $delay) {
            try {
                $interval = new DateInterval($delay);
            } catch (Throwable) {
                throw InvalidConfiguration::because(\sprintf('"%s" is not an ISO 8601 duration, such as P3D.', $delay));
            }

            $at = $reference->add($interval);

            if ($at <= $previous) {
                throw InvalidConfiguration::because('The reminders of an invitation fall due in increasing order, after it is sent.');
            }

            $previous = $at;
            $intervals[] = $interval;
        }

        $this->delays = $intervals;
    }

    public function count(): int
    {
        return \count($this->delays);
    }

    /**
     * When the next reminder falls due, once `$sent` were sent, or `null` when none is left before the closure.
     */
    public function next(DateTimeImmutable $createdAt, int $sent, DateTimeImmutable $closesAt): ?DateTimeImmutable
    {
        $delay = $this->delays[$sent] ?? null;
        $at = $delay instanceof DateInterval ? $createdAt->add($delay) : null;

        return $at instanceof DateTimeImmutable && $at < $closesAt ? $at : null;
    }
}
