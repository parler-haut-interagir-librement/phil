<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

/**
 * A flow declared with attributes, a step of its own and a step of the configuration's flow "reader".
 */
#[AsOnboardingFlow('author', 'Author', 'Welcome', 'site')]
#[AsOnboardingStep('author', 'publish', 'Publish', Completion::Event, 'D', 'app_page', ['id' => 1], '#page', 'app.published', null, false, ['x'], ['y'])]
#[AsOnboardingStep('reader', 'follow', 'Follow', Completion::Checker, checker: PagesChecker::class)]
final class AuthorOnboarding
{
}
