<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the roles the `X-Roles` header of the request lists, separated by commas: Symfony Security's checker in the
 * tests, without a firewall.
 */
final readonly class HeaderAuthorization implements AuthorizationCheckerInterface
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $roles = explode(',', (string) $this->requests->getCurrentRequest()?->headers->get('X-Roles'));

        return \is_string($attribute) && \in_array($attribute, $roles, true);
    }
}
