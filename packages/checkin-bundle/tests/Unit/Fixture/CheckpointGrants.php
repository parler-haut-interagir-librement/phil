<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit\Fixture;

use Override;
use Phil\Checkin\CheckpointId;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the scan attribute on the checkpoints it lists, and nothing else.
 */
final readonly class CheckpointGrants implements AuthorizationCheckerInterface
{
    /**
     * @param list<string> $checkpoints
     */
    public function __construct(
        private array $checkpoints = ['hall'],
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return 'PHIL_CHECKIN_SCAN' === $attribute && $subject instanceof CheckpointId && \in_array($subject->value, $this->checkpoints, true);
    }
}
