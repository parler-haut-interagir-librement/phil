<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Security;

use Override;
use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\Resource\ResourceReference;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The sharing levels as Symfony Security attributes: `is_granted('access.edit', item)`, the item a
 * `ResourceReference` or an `AccessibleItem`; and `access.view_as`, granted to an admin of the space. The subject of
 * the check is the request's: the member viewed, the signed-in member or a visitor.
 *
 * @extends Voter<string, mixed>
 */
final class AccessVoter extends Voter
{
    public const string PREFIX = 'access.';

    public const string VIEW_AS = 'access.view_as';

    public function __construct(
        private readonly ItemAccess $access,
        private readonly SharingLevels $levels,
        private readonly ?ViewAs $viewAs = null,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return self::VIEW_AS === $attribute || (str_starts_with($attribute, self::PREFIX) && $this->levels->has(substr($attribute, \strlen(self::PREFIX))));
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::VIEW_AS === $attribute) {
            return true;
        }

        return $this->supportsAttribute($attribute) && ($subject instanceof ResourceReference || $subject instanceof AccessibleItem);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (self::VIEW_AS === $attribute) {
            $allowed = $this->viewAs?->isAllowed() ?? false;
            $vote?->addReason($allowed ? 'The member is an admin of the space.' : 'Only an admin of the space views the site as a member.');

            return $allowed;
        }

        \assert($subject instanceof ResourceReference || $subject instanceof AccessibleItem);
        $level = substr($attribute, \strlen(self::PREFIX));
        $allowed = $this->access->can($level, $subject);
        $vote?->addReason(\sprintf($allowed ? 'The relation "%s" is given.' : 'The relation "%s" is not given.', $level));

        return $allowed;
    }
}
