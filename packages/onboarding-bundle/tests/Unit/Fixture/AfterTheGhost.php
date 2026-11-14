<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;

/**
 * A declaration read after a service whose class does not exist, which is passed over.
 */
#[AsOnboardingStep('reader', 'ghosted', 'Ghosted')]
final class AfterTheGhost
{
}
