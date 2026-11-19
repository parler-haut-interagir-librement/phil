<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit\Fixture;

use Phil\Assignment\Assignment;
use Phil\Assignment\Placement;

/**
 * A result in a shape a test compares at a glance.
 */
final class Outcomes
{
    /**
     * The unplaced applicants and the outcome of each of their choices.
     *
     * @return array<array-key, array<array-key, string>>
     */
    public static function unplaced(Assignment $assignment): array
    {
        $unplaced = [];

        foreach ($assignment->unplaced as $applicant) {
            $choices = [];

            foreach ($applicant->choices as $choice) {
                $choices[$choice->slot] = $choice->outcome->value;
            }

            $unplaced[$applicant->applicant] = $choices;
        }

        return $unplaced;
    }

    /**
     * The placed applicants, in identifier order.
     *
     * @return list<string>
     */
    public static function placed(Assignment $assignment): array
    {
        return array_map(static fn (Placement $placement): string => $placement->applicant, $assignment->placements);
    }
}
