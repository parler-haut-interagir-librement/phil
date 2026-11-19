<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Security;

use Override;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Asks the voters of the application about the attribute `phil_reactions.react`: with no voter answering, Symfony
 * refuses.
 */
final readonly class AuthorizationCheckerAccess implements ReactionAccess
{
    public function __construct(
        private AuthorizationCheckerInterface $checker,
    ) {
    }

    #[Override]
    public function canReact(ReactionSubject $subject): bool
    {
        return $this->checker->isGranted(Reactions::REACT, $subject);
    }
}
