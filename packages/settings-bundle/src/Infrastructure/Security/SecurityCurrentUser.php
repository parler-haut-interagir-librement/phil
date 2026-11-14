<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Security;

use Override;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The current user as Symfony Security knows them, by their user identifier; a visitor without SecurityBundle.
 */
final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->tokens?->getToken()?->getUser()?->getUserIdentifier();
    }
}
