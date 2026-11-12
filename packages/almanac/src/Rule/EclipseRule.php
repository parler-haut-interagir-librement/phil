<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Astronomy\Eclipses;
use Phil\Almanac\Astronomy\LunarEclipse;
use Phil\Almanac\Astronomy\LunarEclipseKind;
use Phil\Almanac\Astronomy\SolarEclipse;
use Phil\Almanac\Astronomy\SolarEclipseKind;

/**
 * The greatest of every eclipse of the year, in the time zone, of one kind or all: `solar eclipse`, `total solar
 * eclipse`, `annular solar eclipse`, `hybrid solar eclipse`, `partial solar eclipse`, `lunar eclipse`, `total lunar
 * eclipse`, `partial lunar eclipse`, `penumbral lunar eclipse`. A year may have none of a kind.
 */
final readonly class EclipseRule implements WithinTheYear
{
    private function __construct(
        public bool $solar,
        public SolarEclipseKind|LunarEclipseKind|null $kind,
    ) {
    }

    public static function solar(?SolarEclipseKind $kind = null): self
    {
        return new self(true, $kind);
    }

    public static function lunar(?LunarEclipseKind $kind = null): self
    {
        return new self(false, $kind);
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $from = Days::of($year, 1, 1, $zone);
        $until = $from->modify('+1 year');
        $eclipses = $this->solar ? Eclipses::solar($from, $until) : Eclipses::lunar($from, $until);

        return array_values(array_map(
            static fn (SolarEclipse|LunarEclipse $eclipse): DateTimeImmutable => $eclipse->at->setTimezone($zone),
            array_filter($eclipses, fn (SolarEclipse|LunarEclipse $eclipse): bool => null === $this->kind || $eclipse->kind === $this->kind),
        ));
    }

    #[Override]
    public function isInstant(): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return ltrim(\sprintf('%s %s eclipse', $this->kind?->value, $this->solar ? 'solar' : 'lunar'));
    }
}
