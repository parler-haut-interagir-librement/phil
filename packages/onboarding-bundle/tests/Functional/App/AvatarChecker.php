<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional\App;

use Override;
use Phil\OnboardingBundle\Application\Contract\StepChecker;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

/**
 * The application's own data, as a checker reads it: the members who chose an avatar.
 */
#[AsOnboardingStep('author', 'avatar', 'Choose an avatar', Completion::Checker, before: ['first-page'])]
final class AvatarChecker implements StepChecker
{
    /** @var list<string> */
    public array $members = [];

    #[Override]
    public function isComplete(string $user, string $flow, string $step): bool
    {
        return \in_array($user, $this->members, true);
    }
}
