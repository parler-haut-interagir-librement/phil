<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;

/**
 * Every calendar render and every `almanac_dates()` call computes the dates of its period again, four years per
 * definition; astronomical rules — moon phases, Chinese New Year — dominate.
 *
 * @Groups({"almanac"})
 */
final class AlmanacBench
{
    private const array RULES = [
        'full-moon' => 'full moon',
        'chinese-new-year' => 'chinese new year',
        'easter' => 'easter',
        'new-year' => '01-01',
        'mardi-gras' => 'easter - 47 days',
        'patriotes' => 'monday on or before 05-24',
        'labour-day' => '1st monday of september',
        'thanksgiving' => '2nd monday of october',
        'solstice' => 'december solstice',
        'christmas' => '12-25',
    ];

    /**
     * @var array<string, Almanac>
     */
    private array $almanacs;

    private readonly DateTimeImmutable $from;

    private readonly DateTimeImmutable $until;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->from = new DateTimeImmutable('2026-11-01', $zone);
        $this->until = new DateTimeImmutable('2026-12-01', $zone);
        $definitions = [];

        foreach ([1, 2, 3] as $copy) {
            foreach (self::RULES as $key => $rule) {
                $definitions[] = new Definition($key.'-'.$copy, $key, RuleParser::parse($rule), allDay: 'full-moon' !== $key);
            }
        }

        $this->almanacs = [
            'moon, chinese new year, easter' => new Almanac(\array_slice($definitions, 0, 3), $zone),
            '10 definitions' => new Almanac(\array_slice($definitions, 0, 10), $zone),
            '30 definitions' => new Almanac($definitions, $zone),
        ];
    }

    /**
     * @ParamProviders("almanacs")
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @param array{almanac: string} $params
     */
    public function benchMonth(array $params): void
    {
        $this->almanacs[$params['almanac']]->between($this->from, $this->until);
    }

    /**
     * @return Generator<string, array{almanac: string}>
     */
    public function almanacs(): Generator
    {
        yield 'moon, chinese new year, easter' => ['almanac' => 'moon, chinese new year, easter'];
        yield '10 definitions' => ['almanac' => '10 definitions'];
        yield '30 definitions' => ['almanac' => '30 definitions'];
    }
}
