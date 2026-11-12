<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Security;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The signed-in user of Symfony Security, by their user identifier; null for a visitor.
 */
final readonly class TokenDraftOwner implements DraftOwner
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->tokens->getToken()?->getUser()?->getUserIdentifier();
    }
}
