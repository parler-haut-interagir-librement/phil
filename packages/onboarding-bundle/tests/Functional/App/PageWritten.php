<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional\App;

use Phil\OnboardingBundle\Application\Contract\NamesOnboardingUser;

/**
 * An event of the application: a member wrote a page.
 */
final readonly class PageWritten implements NamesOnboardingUser
{
    public function __construct(
        public string $author,
    ) {
    }

    public function onboardingUser(): string
    {
        return $this->author;
    }
}
