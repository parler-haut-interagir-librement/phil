<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

/**
 * A class that declares a step done by a checker without being one, and names none.
 */
#[AsOnboardingStep('reader', 'lost', 'Lost', Completion::Checker)]
final class NotAChecker
{
}
