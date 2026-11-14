<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Contract;

/**
 * Says whether a user has done a step, from the application's own data: a profile filled in, a first page written.
 * It is asked each time the progress is read, until it answers yes, and must answer quickly.
 */
interface StepChecker
{
    public function isComplete(string $user, string $flow, string $step): bool;
}
