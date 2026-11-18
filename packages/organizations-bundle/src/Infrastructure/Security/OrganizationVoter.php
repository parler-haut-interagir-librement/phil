<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Security;

use Override;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Votes on the permissions and the roles a member holds in an organization, given as the organization, its identifier
 * or the text of its identifier:
 *
 * ```php
 * $this->denyAccessUnlessGranted('organization:members.invite', $organization);
 * {% if is_granted('organization_role:owner', organization) %}
 * ```
 *
 * A permission follows the roles of the catalogue and the read-only rule of an archived organization; a role is held
 * or not. Anyone who is not a member, or not signed in, is denied.
 *
 * @extends Voter<string, Organization|OrganizationId|string>
 */
final class OrganizationVoter extends Voter
{
    public const string PERMISSION_PREFIX = 'organization:';

    public const string ROLE_PREFIX = 'organization_role:';

    public function __construct(
        private readonly OrganizationAccess $access,
        private readonly MemberOfUser $members,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, self::PERMISSION_PREFIX) || str_starts_with($attribute, self::ROLE_PREFIX);
    }

    #[Override]
    public function supportsType(string $subjectType): bool
    {
        return \in_array($subjectType, [Organization::class, OrganizationId::class, 'string'], true);
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && ($subject instanceof Organization || $subject instanceof OrganizationId || \is_string($subject));
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $member = $user instanceof UserInterface ? $this->members->memberId($user) : null;
        $organization = $this->organization($subject);

        if (!$member instanceof MemberId || !$organization instanceof OrganizationId) {
            $vote?->addReason('Not a member of a known organization.');

            return false;
        }

        $granted = str_starts_with($attribute, self::ROLE_PREFIX)
            ? $this->access->hasRole($organization, $member, substr($attribute, \strlen(self::ROLE_PREFIX)))
            : $this->access->can($organization, $member, substr($attribute, \strlen(self::PERMISSION_PREFIX)));
        $vote?->addReason(\sprintf('The member %s "%s" in the organization.', $granted ? 'holds' : 'does not hold', $attribute));

        return $granted;
    }

    private function organization(mixed $subject): ?OrganizationId
    {
        if ($subject instanceof Organization) {
            return $subject->id;
        }

        if ($subject instanceof OrganizationId) {
            return $subject;
        }

        try {
            return \is_string($subject) ? OrganizationId::fromString($subject) : null;
        } catch (InvalidOrganization) {
            return null;
        }
    }
}
