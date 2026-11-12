<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Security;

use Override;
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * What the current user may do with a listing, for templates and controllers: `phil_directory.edit` — its author, its
 * editors, the administrators —, `phil_directory.editors` — its author and the administrators — and
 * `phil_directory.publish` — the administrators. The administrators hold the role of the configuration, reached through
 * the role hierarchy when the application has one.
 *
 * @extends Voter<string, Listing>
 */
final class ListingVoter extends Voter
{
    public const string EDIT = 'phil_directory.edit';

    public const string EDITORS = 'phil_directory.editors';

    public const string PUBLISH = 'phil_directory.publish';

    public function __construct(
        private readonly Directory $directory,
        private readonly string $adminRole,
        private readonly ?RoleHierarchyInterface $roles = null,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Listing && \in_array($attribute, [self::EDIT, self::EDITORS, self::PUBLISH], true);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $roles = $token->getRoleNames();
        $actor = new Actor($token->getUserIdentifier(), \in_array($this->adminRole, $this->roles?->getReachableRoleNames($roles) ?? $roles, true));

        return match ($attribute) {
            self::EDIT => $this->directory->mayEdit($subject, $actor),
            self::EDITORS => $this->directory->mayManageEditors($subject, $actor),
            default => $actor->admin,
        };
    }
}
