<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Override;
use Phil\ApiKeysBundle\Infrastructure\Security\RequiresScopeListener;
use Phil\ApiKeysBundle\Infrastructure\Security\ScopeVoter;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Grants `API_SCOPE` on the scopes it is given, and `IS_AUTHENTICATED` when it says so; counts the questions.
 */
final class FixedAuthorization implements AuthorizationCheckerInterface
{
    public int $calls = 0;

    /**
     * @param list<string> $granted
     */
    public function __construct(
        private readonly array $granted,
        private readonly bool $authenticated = true,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        ++$this->calls;

        if (RequiresScopeListener::AUTHENTICATED === $attribute) {
            return $this->authenticated && null === $subject;
        }

        return ScopeVoter::ATTRIBUTE === $attribute && \in_array($subject, $this->granted, true);
    }
}
