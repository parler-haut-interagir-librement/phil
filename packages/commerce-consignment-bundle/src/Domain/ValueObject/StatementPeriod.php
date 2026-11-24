<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;

/**
 * The span of a statement, in UTC and half-open: the entries moved at its start or after, and before its end.
 */
final readonly class StatementPeriod
{
    /** The longest period a statement covers, in days: a year, leap day included, with a margin of a day. */
    public const int MAX_DAYS = 367;

    private function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
    }

    public static function of(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        $utc = new DateTimeZone('UTC');
        $start = $start->setTimezone($utc);
        $end = $end->setTimezone($utc);

        if ($start >= $end) {
            throw InvalidValue::because(\sprintf('A period ends after it starts; %s to %s does not.', $start->format(\DATE_ATOM), $end->format(\DATE_ATOM)));
        }

        if ($end->getTimestamp() - $start->getTimestamp() > self::MAX_DAYS * 86_400) {
            throw InvalidValue::because(\sprintf('A statement covers %d days at most; %s to %s is longer.', self::MAX_DAYS, $start->format(\DATE_ATOM), $end->format(\DATE_ATOM)));
        }

        return new self($start, $end);
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->start && $instant < $this->end;
    }
}
