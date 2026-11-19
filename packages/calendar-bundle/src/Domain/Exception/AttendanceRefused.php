<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Exception;

use DomainException;

/**
 * An attendance the calendar does not take: an event cancelled, not published, full, or one the application's policy
 * turns down.
 */
final class AttendanceRefused extends DomainException
{
    public const string CANCELLED = 'cancelled';

    public const string NOT_PUBLISHED = 'not_published';

    public const string FULL = 'full';

    public const string NOT_AN_OCCURRENCE = 'not_an_occurrence';

    public const string POLICY = 'policy';

    public function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self(match ($reason) {
            self::CANCELLED => 'The event is cancelled.',
            self::NOT_PUBLISHED => 'The event is not published.',
            self::FULL => 'The event is full.',
            self::NOT_AN_OCCURRENCE => 'The event has no occurrence at that moment.',
            default => 'The application does not admit this attendance.',
        }, $reason);
    }
}
