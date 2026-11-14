<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional\App;

use Override;
use Phil\OnboardingBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\CurrentUser as SettingsUser;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The user the request names in its `X-User` header, for the tests, for both bundles.
 */
final readonly class HeaderUser implements CurrentUser, SettingsUser
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->requests->getMainRequest()?->headers->get('X-User');
    }
}
