<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Security;

use Override;
use Phil\ReviewsBundle\Application\Contract\CurrentReviewer;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The member signed in, named by the identifier of Symfony Security's user. An application whose users sign in with
 * their email address gives the bundle its own `CurrentReviewer`: an identifier with `@` is refused, never kept.
 */
final readonly class SecurityCurrentReviewer implements CurrentReviewer
{
    public function __construct(
        private ?TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @throws InvalidReview when the user's identifier is an email address or is malformed
     */
    #[Override]
    public function member(): ?MemberId
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? MemberId::fromString($user->getUserIdentifier()) : null;
    }
}
