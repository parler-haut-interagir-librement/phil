<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Benchmark;

use Generator;
use Phil\Recurrence\RecurrenceRule;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Phil\RecurrenceBundle\Tests\Unit\Fixtures\Translations;

/**
 * The `rrule_text` filter writes a rule in words for every event of a list, building ICU formatters for its day and
 * month names and translating several messages each time.
 *
 * @Groups({"recurrence"})
 */
final class RuleDescriptionBench
{
    private const array RULES = [
        'daily' => 'FREQ=DAILY',
        'two weekdays, a count' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=10',
        'weekdays by position, months, an end' => 'FREQ=YEARLY;BYMONTH=3,6,9,12;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=1,-1;UNTIL=20301231',
    ];

    private readonly RuleDescription $description;

    /**
     * @var array<string, RecurrenceRule>
     */
    private array $rules = [];

    public function __construct()
    {
        $this->description = new RuleDescription(Translations::translator());

        foreach (self::RULES as $name => $rule) {
            $this->rules[$name] = RecurrenceRule::parse($rule);
        }

        // The catalogues are loaded once per request, not per rule.
        $this->description->describe($this->rules['daily'], 'fr');
    }

    /**
     * @ParamProviders("rules")
     *
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @param array{rule: string} $params
     */
    public function benchDescribe(array $params): void
    {
        $this->description->describe($this->rules[$params['rule']], 'fr');
    }

    /**
     * @ParamProviders("lists")
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchList(array $params): void
    {
        $rules = array_values($this->rules);

        for ($index = 0; $index < $params['events']; ++$index) {
            $this->description->describe($rules[$index % \count($rules)], 'fr');
        }
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
     * @return Generator<string, array{events: int}>
     */
    public function lists(): Generator
    {
        yield '10 events' => ['events' => 10];
        yield '50 events' => ['events' => 50];
        yield '200 events' => ['events' => 200];
    }
}
