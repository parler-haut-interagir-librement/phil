<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Security;

use Override;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member of the security token: the user identifier of the signed-in user, null for a visitor. A site names it in
 * `phil_reactions.current_member` only when its user identifiers are stable and never an email address; an identifier
 * containing `@` is refused rather than kept.
 */
final readonly class TokenCurrentMember implements CurrentMember
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @throws InvalidReaction when the user identifier is an email address, or too long
     */
    #[Override]
    public function current(): ?string
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof UserInterface ? Reaction::member($user->getUserIdentifier()) : null;
    }
}
