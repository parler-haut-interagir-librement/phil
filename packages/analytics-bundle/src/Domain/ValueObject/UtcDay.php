<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The day a counter belongs to, **always a UTC day**.
 *
 * The day is fixed once, at ingestion, from the instant the page view arrived, and it is the same for every site.
 * An aggregate is never recomputed in another time zone: a counter only holds a number, not the instants that
 * produced it, so a day boundary moved after the fact cannot be honoured. A UTC day also never has 23 or 25 hours.
 */
final readonly class UtcDay implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * The UTC day of an instant, whatever the time zone the instant carries.
     */
    public static function of(DateTimeInterface $instant): self
    {
        return new self(DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'));
    }

    /**
     * To read back a stored day, in `YYYY-MM-DD` form.
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/D', $value, $parts)
            || !checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])) {
            throw InvalidValue::because(\sprintf('"%s" is not a calendar day in YYYY-MM-DD form.', $value));
        }

        return new self($value);
    }

    /**
     * The first instant of the day, at midnight UTC.
     */
    public function start(): DateTimeImmutable
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $this->value, new DateTimeZone('UTC'));
        \assert($start instanceof DateTimeImmutable);

        return $start;
    }

    public function next(): self
    {
        return self::of($this->start()->modify('+1 day'));
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
