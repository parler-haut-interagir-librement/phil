<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use IntlDateFormatter;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Twig\OpeningHoursExtension;
use Symfony\Component\Clock\MockClock;

/**
 * Every table of hours writes its days and times through ICU, once per place of a listing; the uncached subject
 * builds a formatter per text, which is what the formatters kept by `Intl` save.
 *
 * @BeforeMethods("setUp")
 */
final class OpeningHoursExtensionBench
{
    private readonly OpeningHoursExtension $extension;

    /** @var list<OpeningHours> */
    private array $places = [];

    public function __construct()
    {
        $this->extension = new OpeningHoursExtension(new MockClock(new DateTimeImmutable('2026-12-01 12:00')));
    }

    /**
     * @param array{places: int} $params
     */
    public function setUp(array $params): void
    {
        $place = OpeningHours::of(
            [1 => HoursText::day('09:00-17:00'), 2 => HoursText::day('09:00-17:00'), 3 => HoursText::day('09:00-17:00'), 4 => HoursText::day('09:00-21:00'), 5 => HoursText::day('09:00-21:00'), 6 => HoursText::day('10:00-12:00, 13:00-17:00')],
            [],
            'America/Toronto',
        );
        $this->places = array_fill(0, $params['places'], $place);
        // The formatters are built here, as an earlier table of the process would have.
        $this->extension->week($place, 'fr_CA');
    }

    /**
     * @ParamProviders("listings")
     */
    public function benchWeek(): void
    {
        foreach ($this->places as $place) {
            $this->extension->week($place, 'fr_CA');
        }
    }

    /**
     * The same texts with a new formatter for each, as before formatters were kept.
     *
     * @ParamProviders("listings")
     *
     * @Revs(10)
     */
    public function benchWeekUncached(): void
    {
        foreach ($this->places as $place) {
            foreach ($place->groups() as $group) {
                new IntlDateFormatter('fr_CA', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, 'EEEE')->format(new DateTimeImmutable('2024-01-0'.$group['days'][0]->value));
                new IntlDateFormatter('fr_CA', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, 'EEEE')->format(new DateTimeImmutable('2024-01-0'.array_last($group['days'])->value));

                foreach ($group['hours']->ranges as $range) {
                    new IntlDateFormatter('fr_CA', IntlDateFormatter::NONE, IntlDateFormatter::SHORT, 'UTC')->format($range->opens->minutes * 60);
                    new IntlDateFormatter('fr_CA', IntlDateFormatter::NONE, IntlDateFormatter::SHORT, 'UTC')->format($range->closes->minutes * 60);
                }
            }
        }
    }

    /**
     * @return Generator<string, array{places: int}>
     */
    public function listings(): Generator
    {
        yield '1 place' => ['places' => 1];
        yield '20 places' => ['places' => 20];
        yield '200 places' => ['places' => 200];
    }
}
