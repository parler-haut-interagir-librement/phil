<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Whether the authentication a security token records satisfies an assurance level.
 */
final readonly class AssuranceEvaluator
{
    public function __construct(
        private AssuranceLevels $levels,
        private ClockInterface $clock,
    ) {
    }

    public function isSatisfied(AssuranceLevel $level, TokenInterface $token): bool
    {
        if (!$this->levels->reaches($level, TokenAttribute::string($token, TokenAttribute::ASSURANCE))) {
            return false;
        }

        if (null === $level->maxAge) {
            return true;
        }

        $proofs = $token instanceof AbstractToken ? $token->getAuthenticationProofs() : [];

        return [] !== $proofs && $this->clock->now()->getTimestamp() - max($proofs) <= $level->maxAge;
    }
}
