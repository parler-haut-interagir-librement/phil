<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Security;

use Override;
use Phil\FormsBundle\Application\CurrentSubject;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The person signed in through Symfony Security, by their user identifier; nobody without a token. An identifier
 * with a space, or longer than 255 bytes, is kept by its digest: see `Entry::subjectKey()`.
 */
final readonly class TokenCurrentSubject implements CurrentSubject
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->tokens->getToken()?->getUserIdentifier();
    }
}
