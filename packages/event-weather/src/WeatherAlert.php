<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Url\Url;

/**
 * A watch, warning or advisory of a weather service for the place of the event, as it issued it: its identifier, its
 * event and headline, its severity, its texts, when it takes effect and when it expires in UTC, the area it names and
 * its language. Environment Canada's licence asks that an alert be reproduced without altering its content or intent:
 * the texts are kept as written, never shortened nor rewritten.
 */
final readonly class WeatherAlert
{
    public ?DateTimeImmutable $effective;

    public ?DateTimeImmutable $expires;

    public function __construct(
        public string $id,
        public string $event,
        public AlertSeverity $severity,
        public string $sender,
        ?DateTimeImmutable $effective = null,
        ?DateTimeImmutable $expires = null,
        public ?string $headline = null,
        public ?string $description = null,
        public ?string $instruction = null,
        public ?string $area = null,
        public ?string $language = null,
        public ?Url $web = null,
    ) {
        $utc = new DateTimeZone('UTC');
        $this->effective = $effective?->setTimezone($utc);
        $this->expires = $expires?->setTimezone($utc);
    }

    /**
     * Whether the alert is in effect at some instant of `[$start, $end)`: it took effect before the end, or has no
     * such instant, and expires after the start, or has no expiry.
     */
    public function isInEffectDuring(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return (!$this->effective instanceof DateTimeImmutable || $this->effective < $end) && (!$this->expires instanceof DateTimeImmutable || $this->expires > $start);
    }
}
