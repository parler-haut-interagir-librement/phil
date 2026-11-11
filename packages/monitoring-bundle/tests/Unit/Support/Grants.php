<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * An authorization checker that grants the attributes it is given, and records what it is asked.
 */
final class Grants implements AuthorizationCheckerInterface
{
    /**
     * @var list<mixed>
     */
    public array $asked = [];

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
        $this->asked[] = $attribute;

        return \in_array($attribute, $this->granted, true);
    }
}
