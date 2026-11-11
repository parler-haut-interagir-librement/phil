<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Infrastructure\Form;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Recurrence\Exception\RecurrenceException;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\Weekday;
use Phil\Recurrence\WeekdayNum;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a rule and the fields of its form: the frequency and interval, the weekdays, a day of the month or a
 * weekday at its place in the month, the months, and how it ends. A rule beyond what the fields hold — several
 * month days, set positions, a weekday next to a month day, a week start, an end time — shows what they hold of it,
 * and is given back whole when the fields come back meaning what they showed: saving a form without touching the
 * rule never rewrites it. A change to the fields gives the rule they say.
 *
 * One transformer serves one form, since it remembers the rule it showed.
 *
 * @phpstan-type Fields array{frequency: ?Frequency, interval: ?int, weekdays: list<Weekday>, month_day: ?int, month_weekday: ?int, weekday: ?Weekday, months: list<int>, end: string, count: ?int, until: ?DateTimeImmutable}
 *
 * @implements DataTransformerInterface<RecurrenceRule, Fields>
 */
final class RecurrenceRuleTransformer implements DataTransformerInterface
{
    private ?RecurrenceRule $shown = null;

    /** What the fields shown for `$shown` mean, to know them when they come back. */
    private ?string $meaning = null;

    #[Override]
    public function transform(mixed $value): array
    {
        $placed = null === $value ? null : array_find($value->byDay, static fn (WeekdayNum $day): bool => null !== $day->ordinal);

        $fields = [
            'frequency' => $value?->frequency,
            'interval' => $value->interval ?? 1,
            'weekdays' => null === $value ? [] : array_values(array_map(static fn (WeekdayNum $day): Weekday => $day->weekday, array_filter($value->byDay, static fn (WeekdayNum $day): bool => null === $day->ordinal))),
            'month_day' => $value->byMonthDay[0] ?? null,
            'month_weekday' => $placed?->ordinal,
            'weekday' => $placed?->weekday,
            'months' => $value->byMonth ?? [],
            'end' => match (true) {
                null !== $value?->count => 'count',
                null !== $value?->until => 'until',
                default => 'never',
            },
            'count' => $value?->count,
            'until' => $value?->until,
        ];

        $this->shown = $value;
        $this->meaning = null === $value ? null : $this->meaning($fields);

        return $fields;
    }

    #[Override]
    public function reverseTransform(mixed $value): ?RecurrenceRule
    {
        $rule = $this->rule($value);

        // Fields make a rule exactly when they have a meaning, and a rule shown always has one: the same meaning, or
        // none on both sides, gives back what was shown.
        return $this->meaning($value) === $this->meaning ? $this->shown : $rule;
    }

    /**
     * What fields mean, the end day read as the date field shows it, in the default time zone; `null` for fields that
     * make no rule.
     *
     * @param Fields|null $fields
     */
    private function meaning(?array $fields): ?string
    {
        $until = $fields['until'] ?? null;

        if ($until instanceof DateTimeImmutable) {
            $fields['until'] = new DateTimeImmutable($until->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d'), new DateTimeZone('UTC'));
        }

        try {
            $rule = $this->rule($fields);
        } catch (TransformationFailedException) {
            return null;
        }

        return $rule instanceof RecurrenceRule ? (string) $rule : null;
    }

    /**
     * The rule the fields say.
     *
     * @param Fields|null $value
     */
    private function rule(?array $value): ?RecurrenceRule
    {
        $frequency = $value['frequency'] ?? null;

        if (!$frequency instanceof Frequency) {
            return null;
        }

        $monthly = \in_array($frequency, [Frequency::Monthly, Frequency::Yearly], true);
        $placed = $monthly && null !== ($value['month_weekday'] ?? null) && ($value['weekday'] ?? null) instanceof Weekday;
        $end = $value['end'] ?? 'never';

        try {
            return RecurrenceRule::of(
                $frequency,
                $value['interval'] ?? 1,
                'count' === $end ? $value['count'] ?? throw new TransformationFailedException('A rule that ends after a number of times has its number.') : null,
                'until' === $end ? $value['until'] ?? throw new TransformationFailedException('A rule that ends on a date has its date.') : null,
                'until' === $end,
                [
                    ...\in_array($frequency, [Frequency::Daily, Frequency::Weekly], true) ? array_map(static fn (Weekday $day): WeekdayNum => new WeekdayNum($day), $value['weekdays'] ?? []) : [],
                    ...$placed ? [new WeekdayNum($value['weekday'], $value['month_weekday'])] : [],
                ],
                $monthly && !$placed && null !== ($value['month_day'] ?? null) ? [$value['month_day']] : [],
                Frequency::Yearly === $frequency ? $value['months'] ?? [] : [],
            );
        } catch (RecurrenceException $recurrenceException) {
            throw new TransformationFailedException($recurrenceException->getMessage(), $recurrenceException->getCode(), previous: $recurrenceException);
        }
    }
}
