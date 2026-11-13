<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Security;

use Override;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member of the security token: the user identifier of the signed-in user, null for a visitor. It suits a site
 * whose user identifiers are stable and never an email address; an identifier with `@` is refused rather than kept, and
 * such a site names its own `CurrentMember` in `phil_access.current_member`.
 */
final readonly class TokenCurrentMember implements CurrentMember
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @throws InvalidAccess when the user identifier is an email address, or against the rules
     */
    #[Override]
    public function current(): ?MemberId
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof UserInterface ? MemberId::of($user->getUserIdentifier()) : null;
    }
}
