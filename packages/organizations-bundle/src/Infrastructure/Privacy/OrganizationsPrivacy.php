<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The organizations' part of an access or erasure request of `phil/privacy-bundle`, the data subject being the member:
 * the export lists their memberships, team memberships, unit memberships and the invitations they created; the erasure
 * removes their memberships, team memberships and unit memberships, revokes the invitations they created that could still be used, and forgets them
 * as the creator of the others. A subject that is not a member identifier holds nothing here.
 */
final readonly class OrganizationsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'organizations';

    public function __construct(
        private OrganizationStore $store,
        private MemberErasure $erasure,
        private ?UnitStore $units = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);

        if (!$member instanceof MemberId) {
            return [];
        }

        return array_filter([
            'memberships' => array_map(fn (Membership $membership): array => [
                'organization' => $membership->organizationId->value,
                'name' => $this->store->organization($membership->organizationId)?->name->value,
                'roles' => $membership->roles->names,
                'joined_by' => $membership->method->value,
                'joined_at' => $membership->joinedAt->format(DateTimeInterface::ATOM),
            ], $this->store->membershipsOf($member)),
            'teams' => array_map(fn (TeamMembership $membership): array => [
                'team' => $membership->teamId->value,
                'name' => $this->store->team($membership->teamId)?->name->value,
                'roles' => $membership->roles->names,
                'joined_at' => $membership->joinedAt->format(DateTimeInterface::ATOM),
            ], $this->store->teamMembershipsOf($member)),
            'units' => array_map(fn (UnitMembership $membership): array => [
                'organization' => $membership->organizationId->value,
                'unit' => $membership->unitId->value,
                'name' => $this->units?->unit($membership->unitId)?->name->value,
                'roles' => $membership->roles->names,
                'joined_at' => $membership->joinedAt->format(DateTimeInterface::ATOM),
            ], $this->units?->unitMembershipsOf($member) ?? []),
            'invitations_created' => array_map(static fn (Invitation $invitation): array => [
                'organization' => $invitation->organizationId->value,
                'roles' => $invitation->roles->names,
                'uses' => $invitation->uses,
                'created_at' => $invitation->createdAt->format(DateTimeInterface::ATOM),
                'expires_at' => $invitation->expiresAt->format(DateTimeInterface::ATOM),
            ], $this->erasure->invitationsCreatedBy($member)),
        ]);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);

        if ($member instanceof MemberId) {
            $this->erasure->erase($member);
        }

        return ErasureReport::of(self::MODULE, [
            OrganizationTables::MEMBER => ErasureEffect::Deleted,
            OrganizationTables::MEMBER_ROLE => ErasureEffect::Deleted,
            OrganizationTables::TEAM_MEMBER => ErasureEffect::Deleted,
            OrganizationTables::UNIT_MEMBER => ErasureEffect::Deleted,
            OrganizationTables::INVITATION => ErasureEffect::Pseudonymised,
        ]);
    }

    private function member(DataSubject $subject): ?MemberId
    {
        try {
            return MemberId::fromString($subject->value);
        } catch (InvalidOrganization) {
            return null;
        }
    }
}
