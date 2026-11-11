<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\OpeningHoursSchema;
use Phil\OpeningHours\SpecialHours;
use Phil\OpeningHours\TimeOfDay;
use Phil\OpeningHours\TimeRange;
use Phil\OpeningHoursBundle\Infrastructure\Intl\Intl;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Hours in templates: whether the place is open, when it next opens, the week and the special hours to come written
 * in the request's language, and schema.org. Days and times are written by ICU: `lundi – vendredi`, `9 h 00`,
 * `9:00 AM`; a closed day or period has no hours, `null`, for the template to say so.
 */
final readonly class OpeningHoursExtension
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    #[AsTwigFunction(name: 'opening_hours_open')]
    public function isOpen(?OpeningHours $hours, ?DateTimeInterface $at = null): bool
    {
        return $hours?->isOpenAt($at ?? $this->now()) ?? false;
    }

    #[AsTwigFunction(name: 'opening_hours_next')]
    public function next(?OpeningHours $hours, ?DateTimeInterface $at = null): ?DateTimeImmutable
    {
        return $hours?->nextOpening($at ?? $this->now());
    }

    /**
     * The week, a row a run of days with the same hours. `days` is written as ICU writes it, `lundi – vendredi`; the
     * `heading`, `Lundi – vendredi`, `Monday – Friday`, only has its first letter made a capital, for the start of a
     * line.
     *
     * @return list<array{days: string, heading: string, hours: ?string}>
     */
    #[AsTwigFunction(name: 'opening_hours_week')]
    public function week(?OpeningHours $hours, ?string $locale = null): array
    {
        $name = static fn (DayOfWeek $day): string => Intl::day($day, $locale);

        return array_map(function (array $group) use ($name, $locale): array {
            $days = implode(' – ', array_unique([$name($group['days'][0]), $name(array_last($group['days']))]));

            return ['days' => $days, 'heading' => mb_strtoupper(mb_substr($days, 0, 1)).mb_substr($days, 1), 'hours' => $this->hours($group['hours'], $locale)];
        }, $hours?->groups() ?? []);
    }

    /**
     * The special hours that are not over at a moment, now by default. Their dates are days in the place's time zone,
     * so the moment is read there: at 22:00 in Toronto on Christmas Day, Christmas is not over, whatever day it is in
     * UTC.
     *
     * @return list<array{period: string, label: ?string, hours: ?string}>
     */
    #[AsTwigFunction(name: 'opening_hours_specials')]
    public function specials(?OpeningHours $hours, ?DateTimeInterface $at = null, ?string $locale = null): array
    {
        if (!$hours instanceof OpeningHours) {
            return [];
        }

        $today = DateTimeImmutable::createFromInterface($at ?? $this->now())->setTimezone(new DateTimeZone($hours->timezone))->format('Y-m-d');
        $date = static fn (string $day): string => Intl::date($day, $locale);

        return array_values(array_map(fn (SpecialHours $special): array => [
            'period' => implode(' – ', array_unique([$date($special->from), $date($special->through)])),
            'label' => $special->label,
            'hours' => $this->hours($special->hours, $locale),
        ], array_filter($hours->specials, static fn (SpecialHours $special): bool => $special->through >= $today)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction(name: 'opening_hours_schema')]
    public function schema(?OpeningHours $hours): array
    {
        return $hours instanceof OpeningHours ? OpeningHoursSchema::of($hours) : [];
    }

    private function hours(DayHours $hours, ?string $locale): ?string
    {
        $time = static fn (TimeOfDay $time): string => Intl::time($time, $locale);

        return $hours->isClosed() ? null : implode(', ', array_map(static fn (TimeRange $range): string => $time($range->opens).' – '.$time($range->closes), $hours->ranges));
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
