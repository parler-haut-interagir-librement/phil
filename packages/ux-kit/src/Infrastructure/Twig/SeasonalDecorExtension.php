<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\UxKit\Application\Festivities;
use Phil\UxKit\Application\Festivity;
use Twig\Attribute\AsTwigFunction;

/**
 * What the `seasonal-decor` recipe asks of PHP: `seasonal_decor()`, the holiday of the day and its decor, from the
 * rules of phil/almanac in the configuration; null when the seasonal decor is off, which it is by default.
 */
final readonly class SeasonalDecorExtension
{
    public function __construct(
        private ?Festivities $festivities = null,
        private DateTimeZone $zone = new DateTimeZone('UTC'),
    ) {
    }

    /**
     * The holiday a moment falls in, now without one, a date or a text read in the time zone of the configuration:
     * `'2026-10-31'` is its midnight there; null on a day of none, or when the seasonal decor is off.
     */
    #[AsTwigFunction(name: 'seasonal_decor')]
    public function decor(DateTimeInterface|string|null $at = null): ?Festivity
    {
        return $this->festivities?->at(\is_string($at) ? new DateTimeImmutable($at, $this->zone) : $at);
    }
}
