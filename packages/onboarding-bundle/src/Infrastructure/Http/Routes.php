<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Http;

use Phil\OnboardingBundle\Domain\Flow\Identifier;

/**
 * The names of the endpoints, and the requirement of the identifiers in their paths.
 */
final class Routes
{
    public const string DONE = 'phil_onboarding_done';

    public const string ANSWER = 'phil_onboarding_answer';

    public const string SKIP = 'phil_onboarding_skip';

    public const string DISMISS = 'phil_onboarding_dismiss';

    public const string RESET = 'phil_onboarding_reset';

    public const string IDENTIFIER = Identifier::PATTERN;
}
