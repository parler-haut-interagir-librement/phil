<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Security;

use Phil\DoctrineBehaviours\Contract\BlameResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user Symfony Security authenticated; nobody without a token — a command, a scheduled job — or without the
 * security bundle.
 *
 * A user is named by their identifier, `getUserIdentifier()` — often an e-mail address —, or by what a public method of
 * the user class gives when one is named: a stable identifier that is no personal information, such as a UUID, keeps
 * the blame columns from holding one. A user acting as another through `switch_user` is blamed for what they do, not
 * the user they impersonate.
 */
final readonly class SecurityBlameResolver implements BlameResolver
{
    /**
     * @param ?string $userMethod a public method of the user class, without argument, giving a string, a number or a
     *                            `Stringable`; the user identifier when null
     */
    public function __construct(
        private ?TokenStorageInterface $tokens = null,
        private ?string $userMethod = null,
    ) {
    }

    public function current(): ?string
    {
        $token = $this->tokens?->getToken();

        if (!$token instanceof TokenInterface) {
            return null;
        }

        $user = ($token instanceof SwitchUserToken ? $token->getOriginalToken() : $token)->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        if (null === $this->userMethod) {
            return $user->getUserIdentifier();
        }

        return UserMethod::read($user, $this->userMethod, 'The blame names');
    }
}
