<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Form;

use Override;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\SpecialHours;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Between `OpeningHours` and the fields of their form, a field a day by its name in lowercase; hours with no open day
 * and no special hours are none.
 *
 * @phpstan-type Fields array<string, DayHours|list<SpecialHours>|string|null>
 *
 * @implements DataTransformerInterface<OpeningHours, Fields>
 */
final readonly class OpeningHoursTransformer implements DataTransformerInterface
{
    public function __construct(
        private string $timezone,
    ) {
    }

    #[Override]
    public function transform(mixed $value): array
    {
        $fields = ['specials' => $value->specials ?? [], 'timezone' => $value->timezone ?? $this->timezone];

        foreach (DayOfWeek::cases() as $day) {
            $fields[strtolower($day->name)] = $value?->on($day) ?? DayHours::closed();
        }

        return $fields;
    }

    #[Override]
    public function reverseTransform(mixed $value): ?OpeningHours
    {
        $week = [];

        foreach (DayOfWeek::cases() as $day) {
            $hours = $value[strtolower($day->name)] ?? null;

            if ($hours instanceof DayHours) {
                $week[$day->value] = $hours;
            }
        }

        $specials = \is_array($value['specials'] ?? null) ? $value['specials'] : [];
        $timezone = \is_string($value['timezone'] ?? null) ? $value['timezone'] : $this->timezone;
        $hours = OpeningHours::of($week, $specials, $timezone);

        return $hours->isAlwaysClosed() && [] === $specials ? null : $hours;
    }
}
