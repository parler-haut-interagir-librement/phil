<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * An authorization checker that grants every attribute, or none, and keeps the attributes it was asked.
 */
final class RecordingAuthorization implements AuthorizationCheckerInterface
{
    /** @var list<mixed> */
    public array $asked = [];

    public function __construct(
        private readonly bool $granted,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->asked[] = $attribute;

        return $this->granted;
    }
}
