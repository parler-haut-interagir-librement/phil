<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Security;

use Override;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The participant signed in, named by the identifier of Symfony Security's user. An application whose users sign in
 * with their email address gives the bundle its own `CurrentParticipant`: an identifier with `@` is refused, never kept.
 */
final readonly class SecurityCurrentParticipant implements CurrentParticipant
{
    public function __construct(
        private ?TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @throws InvalidParticipant when the user's identifier is an email address or is malformed
     */
    #[Override]
    public function current(): ?ParticipantId
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? ParticipantId::fromString($user->getUserIdentifier()) : null;
    }
}
