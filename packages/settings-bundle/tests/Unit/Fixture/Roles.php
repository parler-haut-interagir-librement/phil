<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the roles given, and nothing else.
 */
final class Roles implements AuthorizationCheckerInterface
{
    /**
     * @param list<string> $granted
     */
    public function __construct(
        public array $granted = [],
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return \in_array($attribute, $this->granted, true);
    }
}
