<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Contract;

/**
 * An event of the application that says whose step it completes, when it is not the user of the current request — an
 * event dispatched by a worker, or about someone else.
 */
interface NamesOnboardingUser
{
    /**
     * The identifier of the user whose steps the event completes; null for nobody's.
     */
    public function onboardingUser(): ?string;
}
