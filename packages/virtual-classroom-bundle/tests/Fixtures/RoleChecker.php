<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the roles the test names, and keeps every question it was asked.
 */
final class RoleChecker implements AuthorizationCheckerInterface
{
    /** @var list<array{mixed, mixed}> */
    public array $seen = [];

    /**
     * @param list<string> $granted
     */
    public function __construct(
        private readonly array $granted = [],
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->seen[] = [$attribute, $subject];

        return \in_array($attribute, $this->granted, true);
    }
}
