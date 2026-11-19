<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * A person's age on a day, read against a jurisdiction: the whole years completed, the bracket, and the days the two
 * thresholds are reached. It holds no date of birth.
 */
final readonly class AgeAssessment
{
    public function __construct(
        public string $jurisdiction,
        public CalendarDate $today,
        public int $age,
        public AgeBracket $bracket,
        public CalendarDate $consentAgeOn,
        public CalendarDate $majorityOn,
    ) {
    }

    public function needsGuardian(): bool
    {
        return $this->bracket->needsGuardian();
    }
}
