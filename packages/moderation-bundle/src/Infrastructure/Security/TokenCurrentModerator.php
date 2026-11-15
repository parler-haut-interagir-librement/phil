<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Security;

use Override;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Moderator;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The current member as a moderator, under the first configured role whose security attribute they are granted.
 */
final readonly class TokenCurrentModerator implements CurrentModerator
{
    public function __construct(
        private CurrentMember $member,
        private AuthorizationCheckerInterface $authorization,
        private Roles $roles,
    ) {
    }

    /**
     * @phpstan-impure
     */
    #[Override]
    public function moderator(): ?Moderator
    {
        $member = $this->member->member();

        if (!$member instanceof MemberId) {
            return null;
        }

        foreach ($this->roles->all() as $role) {
            if ($this->authorization->isGranted($role->attribute)) {
                return new Moderator($member, $role);
            }
        }

        return null;
    }
}
