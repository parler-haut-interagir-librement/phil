<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

/**
 * A yearly rule by weekday lays out the whole year, 365 or 366 days, for every period it walks, then keeps its weekdays
 * and positions; a year view or a feed that spans several years pays that once per year.
 *
 * @Groups({"recurrence"})
 */
final class YearlyPeriodBench
{
    private const array RULES = [
        'every weekday' => 'FREQ=YEARLY;BYDAY=MO,TU,WE,TH,FR',
        'last Friday' => 'FREQ=YEARLY;BYDAY=-1FR',
        'first and last weekday by BYSETPOS' => 'FREQ=YEARLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=1,-1',
    ];

    /**
     * @var array<string, Recurrence>
     */
    private array $recurrences = [];

    private readonly DateTimeImmutable $from;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->from = new DateTimeImmutable('2026-01-01 00:00', $zone);

        foreach (self::RULES as $name => $rule) {
            $this->recurrences[$name] = Recurrence::of(new DateTimeImmutable('2026-01-02 09:00', $zone), RecurrenceRule::parse($rule));
        }
    }

    /**
     * @ParamProviders({"rules", "spans"})
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @param array{rule: string, years: int} $params
     */
    public function benchYears(array $params): void
    {
        $this->recurrences[$params['rule']]->between($this->from, $this->from->modify(\sprintf('+%d years -1 second', $params['years'])), 10_000);
    }

    /**
     * @return Generator<string, array{rule: string}>
     */
    public function rules(): Generator
    {
        foreach (array_keys(self::RULES) as $name) {
            yield $name => ['rule' => $name];
        }
    }

    /**
     * @return Generator<string, array{years: int}>
     */
    public function spans(): Generator
    {
        yield '1 year' => ['years' => 1];
        yield '5 years' => ['years' => 5];
        yield '20 years' => ['years' => 20];
    }
}
