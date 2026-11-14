<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Override;
use Phil\OnboardingBundle\Application\Contract\TourPreference;

/**
 * Every user is shown the tours of the flows they have not finished nor dismissed.
 */
final readonly class AlwaysTours implements TourPreference
{
    #[Override]
    public function wantsTours(string $user): bool
    {
        return true;
    }
}
