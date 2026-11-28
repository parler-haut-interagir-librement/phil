<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Security;

use Override;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The person signed in through Symfony Security, by the identifier of their user, with the roles of their token.
 */
final readonly class TokenCurrentPerson implements CurrentPerson
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        $identifier = $this->tokens->getToken()?->getUserIdentifier();

        return null === $identifier || '' === $identifier ? null : $identifier;
    }

    #[Override]
    public function roles(): array
    {
        return array_values($this->tokens->getToken()?->getRoleNames() ?? []);
    }
}
