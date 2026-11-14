<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Fixture;

use Closure;
use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The attributes a test grants, and every question asked, with its subject. A policy, when there is one, narrows the
 * attributes granted by their subject.
 */
final class Grants implements AuthorizationCheckerInterface
{
    /**
     * @param list<string>                       $granted
     * @param (Closure(mixed, mixed): bool)|null $policy
     */
    public function __construct(
        public array $granted = [],
        public ?Closure $policy = null,
    ) {
    }

    /** @var list<array{mixed, mixed}> */
    public array $asked = [];

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->asked[] = [$attribute, $subject];

        return \in_array($attribute, $this->granted, true) && (!$this->policy instanceof Closure || ($this->policy)($attribute, $subject));
    }
}
