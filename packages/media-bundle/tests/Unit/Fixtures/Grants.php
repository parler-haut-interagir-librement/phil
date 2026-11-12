<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use Override;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The security of a test: grants the attributes listed, except on the subjects refused, and records each question
 * with its subject.
 */
final class Grants implements AuthorizationCheckerInterface
{
    /** @var list<array{mixed, mixed}> */
    public array $asked = [];

    /**
     * @param list<string> $granted
     */
    public function __construct(
        public array $granted = ['ROLE_ADMIN'],
        /** @var list<mixed> */
        public array $refused = [],
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->asked[] = [$attribute, $subject];

        return \in_array($attribute, $this->granted, true) && !\in_array($subject, $this->refused, true);
    }
}
