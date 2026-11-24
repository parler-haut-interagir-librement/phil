<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional\App;

use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the roles a request names in its `X-Roles` header, comma-separated: the firewall of the test application.
 */
final readonly class RoleChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $roles = explode(',', (string) $this->requests->getCurrentRequest()?->headers->get('X-Roles', ''));

        return \is_string($attribute) && \in_array($attribute, $roles, true);
    }
}
