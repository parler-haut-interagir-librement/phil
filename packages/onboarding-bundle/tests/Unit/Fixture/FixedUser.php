<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Phil\OnboardingBundle\Application\Contract\CurrentUser;

/**
 * The current user a test names, or a visitor.
 */
final class FixedUser implements CurrentUser
{
    public function __construct(
        public ?string $id = null,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->id;
    }
}
