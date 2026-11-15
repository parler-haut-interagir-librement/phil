<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Security;

use Override;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\ModerationMember;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The member of the security token, when its user implements `ModerationMember`. **The user identifier is never read**:
 * it is often an e-mail address, and a member is designated by a stable identifier.
 */
final readonly class TokenCurrentMember implements CurrentMember
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @phpstan-impure
     */
    #[Override]
    public function member(): ?MemberId
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof ModerationMember) {
            return null;
        }

        try {
            return MemberId::of($user->moderationIdentifier());
        } catch (InvalidModeration) {
            return null;
        }
    }
}
