<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Contract;

/**
 * Who the current request is for: the identifier their progress is kept under, or null for a visitor.
 */
interface CurrentUser
{
    public function id(): ?string;
}
