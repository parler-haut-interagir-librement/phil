<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Phil\OnboardingBundle\Application\Contract\StepChecker;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

/**
 * A checker that declares its own step, and so checks it, and a step ticked by hand, which it does not check.
 */
#[AsOnboardingStep('author', 'page', 'Page', Completion::Checker)]
#[AsOnboardingStep('author', 'hand', 'By hand')]
final class PagesChecker implements StepChecker
{
    #[Override]
    public function isComplete(string $user, string $flow, string $step): bool
    {
        return 'ada' === $user;
    }
}
