<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants the attributes listed, and records what it was asked.
 */
final class RecordingChecker implements AuthorizationCheckerInterface
{
    /** @var list<array{mixed, mixed}> */
    public array $asked = [];

    /**
     * @param list<string> $granted
     */
    public function __construct(
        private readonly array $granted,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
    {
        $this->asked[] = [$attribute, $subject];

        return \in_array($attribute, $this->granted, true);
    }
}
