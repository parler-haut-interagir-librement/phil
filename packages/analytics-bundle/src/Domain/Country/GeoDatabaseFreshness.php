<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Country;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Whether a geolocation database is still recent enough to be used.
 *
 * Some database licences bound how long a build may be used after it was compiled. Past that bound the country
 * is counted unknown rather than looked up in a database the licence no longer covers: a stale database fails to
 * "unknown", never to a guess.
 */
final readonly class GeoDatabaseFreshness
{
    private function __construct(
        public int $maxAgeDays,
    ) {
    }

    public static function maxAgeDays(int $days): self
    {
        if ($days < 1) {
            throw InvalidValue::because(\sprintf('A geolocation database is usable for at least one day; %d was given.', $days));
        }

        return new self($days);
    }

    public function isUsable(DateTimeImmutable $builtAt, DateTimeImmutable $now): bool
    {
        if ($builtAt > $now) {
            return false;
        }

        return $now <= $builtAt->modify(\sprintf('+%d days', $this->maxAgeDays));
    }
}
