<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Security;

use Override;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\DataSubjectUser;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The signed-in user, who says which identifier designates them through `DataSubjectUser`.
 *
 * A user class that does not is refused rather than read from `getUserIdentifier()`, which is often an e-mail address.
 */
final readonly class SecurityCurrentDataSubject implements CurrentDataSubject
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?DataSubject
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        if (!$user instanceof DataSubjectUser) {
            throw MisconfiguredPrivacy::userWithoutSubject($user::class);
        }

        return DataSubject::fromString($user->dataSubjectIdentifier());
    }
}
