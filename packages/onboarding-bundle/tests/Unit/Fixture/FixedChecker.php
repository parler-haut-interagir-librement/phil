<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Phil\OnboardingBundle\Application\Contract\StepChecker;

/**
 * A checker that answers what it is told and counts what it is asked.
 */
final class FixedChecker implements StepChecker
{
    /** @var list<string> "user flow step" of each question */
    public array $asked = [];

    public function __construct(
        public bool $answer = false,
    ) {
    }

    #[Override]
    public function isComplete(string $user, string $flow, string $step): bool
    {
        $this->asked[] = $user.' '.$flow.' '.$step;

        return $this->answer;
    }
}
