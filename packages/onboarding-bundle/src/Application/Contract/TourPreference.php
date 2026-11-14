<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Contract;

/**
 * Whether a user wants the guided tours shown to them.
 */
interface TourPreference
{
    public function wantsTours(string $user): bool;
}
