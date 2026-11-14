<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Register;

use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;

/**
 * The register of processing the law asks a business to keep, drawn from the code rather than kept by hand.
 *
 * - **Its activities** are the rules of the retention schedule that hold personal information: each says why the store
 *   holds it, who receives it, which service providers handle it and where it is kept, beside its period.
 * - **Its fields** are every field declared `#[PersonalData]` in the given classes, by category, with the retention
 *   rule each names and, when the application has a schedule, its store, period, mechanism and answer to erasure.
 *
 * What is missing is named rather than left blank: the activities without a purpose or a location, and the fields
 * that name a rule the schedule does not hold.
 */
final readonly class ProcessingRegister
{
    public function __construct(
        private ?RetentionScheduleSource $schedule = null,
    ) {
    }

    /**
     * @param iterable<class-string> $classes
     *
     * @return list<RegisterEntry> by category, then class, then property
     */
    public function entries(iterable $classes): array
    {
        $schedule = $this->schedule?->load();
        $fields = [];

        // A field declared on a parent is read through every child: kept once, under the class that declares it.
        foreach ($classes as $class) {
            foreach (DeclaredPersonalFields::of($class) as $field) {
                $fields[$field->class][$field->property] = $field;
            }
        }

        $entries = [];

        foreach ($fields as $properties) {
            foreach ($properties as $field) {
                $entries[] = new RegisterEntry($field, $this->ruleOf($field, $schedule));
            }
        }

        usort($entries, static fn (RegisterEntry $a, RegisterEntry $b): int => [$a->field->category->value, $a->field->class, $a->field->property] <=> [$b->field->category->value, $b->field->class, $b->field->property]);

        return $entries;
    }

    /**
     * The rules of the schedule that hold personal information or that a field names, in schedule order; none without a
     * schedule.
     *
     * @param iterable<class-string> $classes
     *
     * @return list<ProcessingActivity>
     */
    public function activities(iterable $classes): array
    {
        $schedule = $this->schedule?->load();

        if (!$schedule instanceof RetentionSchedule) {
            return [];
        }

        $byRule = [];

        foreach ($this->entries($classes) as $entry) {
            if ($entry->rule instanceof RetentionRule) {
                $byRule[$entry->rule->id][] = $entry->field;
            }
        }

        $activities = [];

        foreach ($schedule->rules() as $rule) {
            $fields = $byRule[$rule->id] ?? [];

            if ($rule->holdsPersonalInformation() || [] !== $fields) {
                usort($fields, static fn (PersonalField $a, PersonalField $b): int => [$a->class, $a->property] <=> [$b->class, $b->property]);
                $activities[] = new ProcessingActivity($rule, $fields);
            }
        }

        return $activities;
    }

    /**
     * What keeps the register from being complete, one sentence each: an activity without a purpose or a location, a
     * field that names a rule the schedule does not hold. Empty, the register answers every question it is asked.
     *
     * @param iterable<class-string> $classes
     *
     * @return list<string>
     */
    public function gaps(iterable $classes): array
    {
        // Without a schedule there is no activity, and no rule a field could miss.
        if (!$this->schedule instanceof RetentionScheduleSource) {
            return [];
        }

        $classes = [...$classes];
        $gaps = [];

        foreach ($this->activities($classes) as $activity) {
            if (null === $activity->rule->processing->purpose) {
                $gaps[] = \sprintf('Rule %s (%s) holds personal information and states no purpose.', $activity->rule->id, $activity->rule->store);
            }

            if (null === $activity->rule->processing->location) {
                $gaps[] = \sprintf('Rule %s (%s) holds personal information and states no location.', $activity->rule->id, $activity->rule->store);
            }
        }

        foreach ($this->entries($classes) as $entry) {
            if (null !== $entry->field->retention && !$entry->rule instanceof RetentionRule) {
                $gaps[] = \sprintf('%s::$%s names the retention rule %s, which the schedule does not hold.', $entry->field->class, $entry->field->property, $entry->field->retention);
            }
        }

        return $gaps;
    }

    private function ruleOf(PersonalField $field, ?RetentionSchedule $schedule): ?RetentionRule
    {
        return null !== $field->retention && $schedule instanceof RetentionSchedule && $schedule->has($field->retention) ? $schedule->rule($field->retention) : null;
    }
}
