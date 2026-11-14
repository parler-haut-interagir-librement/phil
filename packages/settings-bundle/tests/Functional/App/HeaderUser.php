<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional\App;

use Override;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The user the request names in its `X-User` header, for the tests.
 */
final readonly class HeaderUser implements CurrentUser
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
