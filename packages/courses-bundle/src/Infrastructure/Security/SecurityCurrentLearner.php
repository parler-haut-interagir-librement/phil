<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Security;

use Override;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The learner signed in, named by the identifier of Symfony Security's user. An application whose users sign in with
 * their e-mail address gives the bundle its own `CurrentLearner`: an identifier with `@` is refused, never kept.
 */
final readonly class SecurityCurrentLearner implements CurrentLearner
{
    public function __construct(
        private ?TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @throws InvalidCourse when the user's identifier is an e-mail address or is malformed
     */
    #[Override]
    public function current(): ?LearnerId
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? LearnerId::of($user->getUserIdentifier()) : null;
    }
}
