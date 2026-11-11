<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Phil\OpeningHours\Exception\UnreadableOpeningHours;

/**
 * Hours as an array of texts, for one JSON column, and back:
 *
 * ```json
 * {"timezone": "America/Toronto",
 *  "week": {"1": "09:00-17:00", "2": "09:00-17:00", "6": "10:00-16:00"},
 *  "specials": [{"from": "2026-12-25", "through": "2026-12-25", "hours": "", "label": "Christmas"}]}
 * ```
 */
final class OpeningHoursArray
{
    /**
     * @return array{timezone: string, week: array<int, string>, specials: list<array{from: string, through: string, hours: string, label: ?string}>}
     */
    public static function from(OpeningHours $hours): array
    {
        $week = [];

        foreach (DayOfWeek::cases() as $day) {
            if (!$hours->on($day)->isClosed()) {
                $week[$day->value] = $hours->on($day)->toText();
            }
        }

        return [
            'timezone' => $hours->timezone,
            'week' => $week,
            'specials' => array_map(static fn (SpecialHours $special): array => ['from' => $special->from, 'through' => $special->through, 'hours' => $special->hours->toText(), 'label' => $special->label], $hours->specials),
        ];
    }

    public static function to(mixed $data): OpeningHours
    {
        if (!\is_array($data) || !\is_string($data['timezone'] ?? null) || !\is_array($data['week'] ?? []) || !\is_array($data['specials'] ?? []) || !array_is_list($data['specials'] ?? [])) {
            throw UnreadableOpeningHours::because('it is not an array with a time zone, a week and a list of special hours');
        }

        try {
            $week = [];

            foreach ($data['week'] ?? [] as $day => $text) {
                $dayOfWeek = DayOfWeek::tryFrom((int) $day) ?? throw UnreadableOpeningHours::because(\sprintf('"%s" is no day of the week', $day));
                $week[$dayOfWeek->value] = HoursText::day(self::text($text));
            }

            $specials = array_map(static fn (mixed $special): SpecialHours => \is_array($special)
                ? SpecialHours::of(self::text($special['from'] ?? null), self::text($special['through'] ?? null), HoursText::day(self::text($special['hours'] ?? '')), null === ($special['label'] ?? null) ? null : self::text($special['label']))
                : throw UnreadableOpeningHours::because('special hours are not an object'), $data['specials'] ?? []);

            return OpeningHours::of($week, $specials, $data['timezone']);
        } catch (InvalidOpeningHours $invalidOpeningHours) {
            throw UnreadableOpeningHours::because($invalidOpeningHours->getMessage());
        }
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : throw UnreadableOpeningHours::because('a value is not a text');
    }
}
