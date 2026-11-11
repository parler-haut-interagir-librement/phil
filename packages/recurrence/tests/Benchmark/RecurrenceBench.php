<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

/**
 * Every calendar view and every poll of an `.ics` feed asks a rule for the occurrences of a short window, often years
 * after the rule started; the cost of that window must not grow with the age of the series.
 *
 * @Groups({"recurrence"})
 */
final class RecurrenceBench
{
    private const array RULES = [
        'monthly MO,TU,WE' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE',
        'yearly 1st and 15th' => 'FREQ=YEARLY;BYMONTHDAY=1,15',
        'monthly MO,TU,WE, COUNT=5000' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE;COUNT=5000',
    ];

    /**
     * @var array<string, Recurrence>
     */
    private array $recurrences = [];

    private readonly DateTimeImmutable $from;

    private readonly DateTimeImmutable $through;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->from = new DateTimeImmutable('2026-11-01 00:00', $zone);
        $this->through = new DateTimeImmutable('2026-11-30 23:59', $zone);

        foreach (self::RULES as $name => $rule) {
            foreach ([0, 10, 50] as $age) {
                $this->recurrences[$name.' '.$age] = Recurrence::of(new DateTimeImmutable(\sprintf('%d-01-05 19:00', 2026 - $age), $zone), RecurrenceRule::parse($rule));
            }
        }
    }

    /**
     * @ParamProviders({"rules", "ages"})
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @param array{rule: string, age: int} $params
     */
    public function benchMonthWindow(array $params): void
    {
        $this->recurrences[$params['rule'].' '.$params['age']]->between($this->from, $this->through);
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
     * @return Generator<string, array{age: int}>
     */
    public function ages(): Generator
    {
        yield 'started this year' => ['age' => 0];
        yield 'started 10 years ago' => ['age' => 10];
        yield 'started 50 years ago' => ['age' => 50];
    }
}
