<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\WeekdayNum;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A rule in words, in a language: *Every 2 weeks on Monday and Wednesday, 10 times*, *Tous les mois le deuxième
 * mardi, jusqu’au 31 décembre 2026*. The sentences are ICU messages of the `phil_recurrence` domain; days, months and
 * dates are written by ICU, with one formatter per language, pattern and time zone, built once and kept. In a worker
 * that lives across requests, 64 formatters are kept at most: past them, they are built again, so that languages and
 * time zones taken from requests cannot grow the memory without end.
 */
final class RuleDescription
{
    /** The formatters kept at most. */
    private const int KEPT = 64;

    /** @var array<string, array<string, array<string, IntlDateFormatter>>> by language, pattern and time zone */
    private array $formatters = [];

    /** How many formatters are kept. */
    private int $kept = 0;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param ?string       $locale   the translator's when null, for the sentences and the names of days and months alike
     * @param ?DateTimeZone $timezone the zone of the recurrence's first occurrence, in which an end time is a day; the
     *                                end's own zone, UTC for a rule read from text, when null
     */
    public function describe(RecurrenceRule $rule, ?string $locale = null, ?DateTimeZone $timezone = null): string
    {
        $locale ??= $this->translator->getLocale();
        $parts = [$this->trans('frequency.'.strtolower($rule->frequency->value), ['interval' => $rule->interval], $locale)];

        if ([] !== $rule->byDay) {
            $parts[] = $this->trans('by_day', ['days' => $this->list(array_map(fn (WeekdayNum $day): string => $this->day($day, $locale), $rule->byDay), $locale)], $locale);
        }

        if ([] !== $rule->byMonthDay) {
            $parts[] = $this->trans('by_month_day', ['days' => $this->list(array_map(fn (int $day): string => $this->trans($day >= 1 ? 'month_day' : 'month_day_from_end', ['n' => abs($day)], $locale), $rule->byMonthDay), $locale)], $locale);
        }

        if ([] !== $rule->byMonth) {
            $parts[] = $this->trans('by_month', ['months' => $this->list(array_map(fn (int $month): string => $this->format($locale, 'MMMM', new DateTimeImmutable(\sprintf('2024-%02d-01', $month), new DateTimeZone('UTC'))), $rule->byMonth), $locale)], $locale);
        }

        if ([] !== $rule->bySetPos) {
            $parts[] = $this->trans('by_set_position', ['positions' => $this->list(array_map(fn (int $position): string => $this->trans('set_position', $this->place($position), $locale), $rule->bySetPos), $locale)], $locale);
        }

        $sentence = implode(' ', $parts);

        if (null !== $rule->count) {
            return $sentence.$this->trans('count', ['count' => $rule->count], $locale);
        }

        if ($rule->until instanceof DateTimeImmutable) {
            return $sentence.$this->trans('until', ['date' => $this->format($locale, 'd MMMM y', $rule->untilIsDate || !$timezone instanceof DateTimeZone ? $rule->until : $rule->until->setTimezone($timezone))], $locale);
        }

        return $sentence;
    }

    private function day(WeekdayNum $day, string $locale): string
    {
        $name = $this->format($locale, 'EEEE', new DateTimeImmutable(\sprintf('2024-01-%02d', $day->weekday->iso()), new DateTimeZone('UTC')));

        return null === $day->ordinal ? $this->trans('day', ['day' => $name], $locale) : $this->trans('nth_day', ['day' => $name, ...$this->place($day->ordinal)], $locale);
    }

    /**
     * A place from the start or the end, as the messages read it: how far, and from which side.
     *
     * @return array{n: int, from_end: string}
     */
    private function place(int $position): array
    {
        return ['n' => abs($position), 'from_end' => $position >= 1 ? 'no' : 'yes'];
    }

    /**
     * @param non-empty-list<string> $items
     */
    private function list(array $items, ?string $locale): string
    {
        $last = $items[\count($items) - 1];
        $before = \array_slice($items, 0, -1);

        return [] === $before ? $last : implode(', ', $before).$this->trans('and', [], $locale).$last;
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function trans(string $key, array $parameters, ?string $locale): string
    {
        return $this->translator->trans('recurrence.'.$key, $parameters, 'phil_recurrence', $locale);
    }

    private function format(string $locale, string $pattern, DateTimeImmutable $date): string
    {
        $zone = $date->getTimezone();
        $formatter = $this->formatters[$locale][$pattern][$zone->getName()] ?? $this->keep($locale, $pattern, $zone);
        $text = $formatter->format($date);

        return false === $text ? '' : $text;
    }

    private function keep(string $locale, string $pattern, DateTimeZone $zone): IntlDateFormatter
    {
        if ($this->kept >= self::KEPT) {
            $this->formatters = [];
            $this->kept = 0;
        }

        ++$this->kept;

        return $this->formatters[$locale][$pattern][$zone->getName()] = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, null, $pattern);
    }
}
