<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Security;

use LogicException;
use Override;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member of the security token: the user identifier of the signed-in user, `null` for a visitor. A member is named
 * by a stable identifier, never by personal information: the container is only built with this resolver when the
 * application declares `phil_terms.stable_identifiers`, and a user identifier shaped like an email address is still
 * refused here, should the declaration be wrong. The application whose users sign in by email names a resolver of its
 * own in `phil_terms.member_resolver`.
 */
final readonly class TokenMemberResolver implements MemberResolver
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        $identifier = $user->getUserIdentifier();

        if (false !== filter_var($identifier, \FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('The user identifier of the security token is an email address, although phil_terms.stable_identifiers declares it never is: name a resolver that gives a stable identifier in phil_terms.member_resolver.');
        }

        return $identifier;
    }
}
