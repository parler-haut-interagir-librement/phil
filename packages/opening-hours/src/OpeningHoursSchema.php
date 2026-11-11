<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

/**
 * Hours as schema.org's `openingHoursSpecification`: one `OpeningHoursSpecification` per range of the week, for the
 * days that share it, and one per range of special hours with its `validFrom` and `validThrough`. Special hours that
 * close the place open and close at `00:00`, as search engines read it.
 */
final class OpeningHoursSchema
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function of(OpeningHours $hours): array
    {
        $specifications = [];
        $byRange = [];

        foreach (DayOfWeek::cases() as $day) {
            foreach ($hours->on($day)->ranges as $range) {
                $byRange[(string) $range][] = $day->schemaOrg();
            }
        }

        foreach ($byRange as $range => $days) {
            [$opens, $closes] = explode('-', $range);
            $specifications[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $opens, 'closes' => $closes];
        }

        foreach ($hours->specials as $special) {
            foreach ($special->hours->isClosed() ? [null] : $special->hours->ranges as $range) {
                $specifications[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'opens' => null === $range ? '00:00' : (string) $range->opens,
                    'closes' => null === $range ? '00:00' : (string) $range->closes,
                    'validFrom' => $special->from,
                    'validThrough' => $special->through,
                ];
            }
        }

        return $specifications;
    }
}
