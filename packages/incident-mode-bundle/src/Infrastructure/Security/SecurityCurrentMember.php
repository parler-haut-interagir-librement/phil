<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Security;

use Override;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member signed in, named by the identifier of Symfony Security's user; a visitor without a token or a user.
 *
 * An application whose users sign in with their e-mail address gives the bundle its own `CurrentMember`: the
 * administration refuses an identifier that looks like an address as the actor of an incident.
 */
final readonly class SecurityCurrentMember implements CurrentMember
{
    public function __construct(
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    #[Override]
    public function identifier(): ?string
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? $user->getUserIdentifier() : null;
    }
}
