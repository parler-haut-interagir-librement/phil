<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\Recurrence\RecurrenceRule;

/**
 * The end of a series is worked out again on every save that changes an event's schedule, for the repository to find
 * the events of a period; a rule with a large count is read to its last occurrence. The event stops repeating before
 * each rule is given again, for every revolution to work the end out rather than find it kept.
 *
 * @Groups({"calendar"})
 */
final class SeriesEndBench
{
    private readonly Event $event;

    /**
     * @var array<string, RecurrenceRule>
     */
    private array $rules = [];

    public function __construct()
    {
        $start = new DateTimeImmutable('2026-01-06 19:00', new DateTimeZone('America/Toronto'));
        $this->event = Event::schedule('Game night', $start, $start->modify('+3 hours'));

        foreach (array_keys(iterator_to_array($this->rules())) as $name) {
            $this->rules[$name] = RecurrenceRule::parse($name);
        }
    }

    /**
     * @ParamProviders("rules")
     *
     * @Revs(3)
     *
     * @Iterations(5)
     *
     * @param array{rule: string} $params
     */
    public function benchRepeat(array $params): void
    {
        $this->event->repeat(null);
        $this->event->repeat($this->rules[$params['rule']]);
    }

    /**
     * @return Generator<string, array{rule: string}>
     */
    public function rules(): Generator
    {
        foreach ([
            'FREQ=WEEKLY;BYDAY=TU,TH;COUNT=10',
            'FREQ=WEEKLY;BYDAY=TU,TH;COUNT=1000',
            'FREQ=DAILY;COUNT=9999',
            'FREQ=WEEKLY;BYDAY=TU,TH;UNTIL=20271231',
            'FREQ=WEEKLY;BYDAY=TU,TH;UNTIL=21001231',
            'FREQ=WEEKLY;BYDAY=TU,TH;UNTIL=99991231',
        ] as $rule) {
            yield $rule => ['rule' => $rule];
        }
    }
}
