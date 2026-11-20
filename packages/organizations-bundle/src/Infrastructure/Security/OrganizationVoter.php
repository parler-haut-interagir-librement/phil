<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Security;

use Override;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Votes on the permissions and the roles a member holds in an organization, given as the organization, its identifier
 * or the text of its identifier, and in a unit, given as the unit, its identifier or the text of its identifier:
 *
 * ```php
 * $this->denyAccessUnlessGranted('organization:members.invite', $organization);
 * {% if is_granted('organization_role:owner', organization) %}
 * $this->denyAccessUnlessGranted('unit:unit.manage', $unit);
 * {% if is_granted('unit_role:manager', unit) %}
 * ```
 *
 * A permission follows the roles of the catalogue and the read-only rule of an archived organization; a unit permission
 * follows the unit roles held in the unit or above it, as `UnitAccess` reads them. A role is held or not, a unit role
 * in the unit or above it. Anyone who is not a member, or not signed in, is denied.
 *
 * @extends Voter<string, Organization|OrganizationId|Unit|UnitId|string>
 */
final class OrganizationVoter extends Voter
{
    public const string PERMISSION_PREFIX = 'organization:';

    public const string ROLE_PREFIX = 'organization_role:';

    public const string UNIT_PERMISSION_PREFIX = 'unit:';

    public const string UNIT_ROLE_PREFIX = 'unit_role:';

    public function __construct(
        private readonly OrganizationAccess $access,
        private readonly MemberOfUser $members,
        private readonly ?UnitAccess $units = null,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, self::PERMISSION_PREFIX) || str_starts_with($attribute, self::ROLE_PREFIX) || $this->isUnitAttribute($attribute);
    }

    #[Override]
    public function supportsType(string $subjectType): bool
    {
        return \in_array($subjectType, [Organization::class, OrganizationId::class, Unit::class, UnitId::class, 'string'], true);
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($this->isUnitAttribute($attribute)) {
            return $subject instanceof Unit || $subject instanceof UnitId || \is_string($subject);
        }

        return $this->supportsAttribute($attribute) && ($subject instanceof Organization || $subject instanceof OrganizationId || \is_string($subject));
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $member = $user instanceof UserInterface ? $this->members->memberId($user) : null;

        if ($this->isUnitAttribute($attribute)) {
            return $this->voteOnUnit($attribute, $subject, $member, $vote);
        }

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

    private function isUnitAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, self::UNIT_PERMISSION_PREFIX) || str_starts_with($attribute, self::UNIT_ROLE_PREFIX);
    }

    private function voteOnUnit(string $attribute, mixed $subject, ?MemberId $member, ?Vote $vote): bool
    {
        $unit = $this->unit($subject);

        if (!$member instanceof MemberId || !$unit instanceof UnitId || !$this->units instanceof UnitAccess) {
            $vote?->addReason('Not a member of a known unit.');

            return false;
        }

        $granted = str_starts_with($attribute, self::UNIT_ROLE_PREFIX)
            ? $this->units->hasRole($unit, $member, substr($attribute, \strlen(self::UNIT_ROLE_PREFIX)))
            : $this->units->can($unit, $member, substr($attribute, \strlen(self::UNIT_PERMISSION_PREFIX)));
        $vote?->addReason(\sprintf('The member %s "%s" in the unit.', $granted ? 'holds' : 'does not hold', $attribute));

        return $granted;
    }

    private function unit(mixed $subject): ?UnitId
    {
        if ($subject instanceof Unit) {
            return $subject->id;
        }

        if ($subject instanceof UnitId) {
            return $subject;
        }

        try {
            return \is_string($subject) ? UnitId::fromString($subject) : null;
        } catch (InvalidOrganization) {
            return null;
        }
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
