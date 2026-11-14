<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

/**
 * Where a user stands on one step.
 */
enum StepState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
