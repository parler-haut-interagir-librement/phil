<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The application's retention schedule: the single authority on how long a personal record is kept.
 *
 * The bundle does not read the schedule's file — markdown table, YAML, database — and does not decide its
 * lines. It holds them, refuses a duplicate identifier, and turns the lines a job enforces into purge orders.
 */
final readonly class RetentionSchedule
{
    /**
     * @var array<string, RetentionRule> by identifier, in schedule order
     */
    private array $rules;

    /**
     * @param iterable<RetentionRule> $rules
     */
    public function __construct(iterable $rules)
    {
        $byId = [];

        foreach ($rules as $rule) {
            if (isset($byId[$rule->id])) {
                throw InvalidPrivacyValue::because(\sprintf('The retention schedule defines rule %s twice.', $rule->id));
            }

            $byId[$rule->id] = $rule;
        }

        $this->rules = $byId;
    }

    public function has(string $id): bool
    {
        return isset($this->rules[$id]);
    }

    public function rule(string $id): RetentionRule
    {
        return $this->rules[$id] ?? throw InvalidPrivacyValue::because(\sprintf('The retention schedule has no rule %s.', $id));
    }

    /**
     * @return list<RetentionRule>
     */
    public function rules(): array
    {
        return array_values($this->rules);
    }

    /**
     * One order per rule enforced by a job whose period is fixed, in schedule order.
     *
     * @return list<PurgeOrder>
     */
    public function purgeOrders(DateTimeImmutable $now): array
    {
        $orders = [];

        foreach ($this->rules as $rule) {
            $cutoff = Mechanism::Job === $rule->mechanism ? $rule->period->cutoff($now) : null;

            if (null !== $cutoff) {
                $orders[] = new PurgeOrder($rule->id, $rule->store, $cutoff);
            }
        }

        return $orders;
    }

    /**
     * The rules a job is meant to enforce but that yield no cutoff: their period runs until an event or is not
     * fixed yet. Each is a job the application runs on its own terms, or a blank to report.
     *
     * @return list<RetentionRule>
     */
    public function jobsWithoutCutoff(): array
    {
        return array_values(array_filter(
            $this->rules,
            static fn (RetentionRule $rule): bool => Mechanism::Job === $rule->mechanism && !$rule->period->isFixed(),
        ));
    }
}
