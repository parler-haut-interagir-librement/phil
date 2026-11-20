<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Functional\App;

use Override;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Http\TokenPageController;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lets alice manage the tokens of the organisation acme, and nobody those of any other owner.
 *
 * @extends Voter<string, TokenOwner>
 */
final class OrganizationVoter extends Voter
{
    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return TokenPageController::MANAGE === $attribute && $subject instanceof TokenOwner;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return 'alice@example.com' === $token->getUserIdentifier() && $subject->equals(new TokenOwner('organization', 'acme'));
    }
}
