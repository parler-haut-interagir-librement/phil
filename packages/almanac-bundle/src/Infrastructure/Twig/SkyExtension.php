<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Phil\Almanac\Astronomy\Eclipses;
use Phil\Almanac\Astronomy\LunarEclipse;
use Phil\Almanac\Astronomy\SolarDay;
use Phil\Almanac\Astronomy\SolarEclipse;
use Phil\Almanac\Astronomy\SunTimes;
use Phil\Almanac\Place;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\Places;
use Twig\Attribute\AsTwigFunction;

/**
 * The sky in Twig: `sun_times(place, day)` and `almanac_eclipses(from, until, body)`.
 */
final readonly class SkyExtension
{
    public function __construct(
        private Places $places,
        private AlmanacFactory $almanac,
    ) {
    }

    /**
     * The solar day of a place — a name of `phil_almanac.places`, or a `Place` — on the date a day falls on in the
     * place's time zone; text is read in that time zone: `'today'` is the place's today.
     */
    #[AsTwigFunction('sun_times')]
    public function sunTimes(Place|string $place, DateTimeInterface|string $day = 'today'): SolarDay
    {
        $place = \is_string($place) ? $this->places->get($place) : $place;

        return SunTimes::on($place, \is_string($day) ? new DateTimeImmutable($day, $place->zone) : $day);
    }

    /**
     * The eclipses whose greatest falls in a period, its end excluded, in order: `'solar'`, `'lunar'`, or both. A
     * bound is a date, or text read in the almanac's time zone.
     *
     * @return list<SolarEclipse|LunarEclipse>
     */
    #[AsTwigFunction('almanac_eclipses')]
    public function eclipses(DateTimeInterface|string $from, DateTimeInterface|string $until, ?string $body = null): array
    {
        $from = $this->instant($from);
        $until = $this->instant($until);
        $eclipses = match ($body) {
            null => [...Eclipses::solar($from, $until), ...Eclipses::lunar($from, $until)],
            'solar' => Eclipses::solar($from, $until),
            'lunar' => Eclipses::lunar($from, $until),
            default => throw new InvalidArgumentException(\sprintf('An eclipse is "solar" or "lunar", not "%s".', mb_substr($body, 0, 100))),
        };

        usort($eclipses, static fn (SolarEclipse|LunarEclipse $a, SolarEclipse|LunarEclipse $b): int => $a->at->getTimestamp() <=> $b->at->getTimestamp());

        return $eclipses;
    }

    private function instant(DateTimeInterface|string $bound): DateTimeImmutable
    {
        return \is_string($bound) ? new DateTimeImmutable($bound, $this->almanac->zone()) : DateTimeImmutable::createFromInterface($bound);
    }
}
