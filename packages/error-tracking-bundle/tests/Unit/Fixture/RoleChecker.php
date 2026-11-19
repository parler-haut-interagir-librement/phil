<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the roles it was given, nothing else.
 */
final readonly class RoleChecker implements AuthorizationCheckerInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private array $roles,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return \in_array($attribute, $this->roles, true);
    }
}
