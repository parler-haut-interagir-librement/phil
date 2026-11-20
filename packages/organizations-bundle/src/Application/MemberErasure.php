<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Domain\Event\InvitationRevoked;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\OrganizationLeftWithoutOwner;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRemoved;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Phil\OrganizationsBundle\Domain\Status\LeaveReason;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;

/**
 * Erases a member from every organization, for a right to erasure, and says what it kept.
 *
 * A right of the person is never refused for the organization's sake: the last owner is removed too, and the
 * organization is announced by `OrganizationLeftWithoutOwner`, for an operator to name a new owner. The member's team
 * and unit memberships go with their memberships; the invitations they created and that could still be used are revoked, and
 * every invitation they created forgets them. Erasing a member twice changes nothing more.
 */
final readonly class MemberErasure
{
    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private ClockInterface $clock,
        private ?UnitStore $units = null,
    ) {
    }

    public function erase(MemberId $member): void
    {
        $this->work->run(function (EventRecorder $events) use ($member): void {
            $now = $this->clock->now();

            foreach ($this->store->membershipsOf($member) as $membership) {
                $organizationId = $membership->organizationId;
                $this->store->organization($organizationId, true);

                foreach ($this->store->teamMembershipsOf($member, $organizationId) as $teamMembership) {
                    $events->record(new TeamMemberRemoved($organizationId->value, $teamMembership->teamId->value, $member->value, $teamMembership->roles->names));
                }

                foreach ($this->units?->unitMembershipsOf($member, $organizationId) ?? [] as $unitMembership) {
                    $events->record(new UnitMemberRemoved($organizationId->value, $unitMembership->unitId->value, $member->value, $unitMembership->roles->names));
                }

                $this->store->deleteMembership($organizationId, $member);
                $events->record(new MemberLeft($organizationId->value, $member->value, $membership->roles->names, LeaveReason::Erased));

                if ($membership->roles->has(RoleCatalog::OWNER) && 0 === $this->store->countWithRole($organizationId, RoleCatalog::OWNER)) {
                    $events->record(new OrganizationLeftWithoutOwner($organizationId->value));
                }
            }

            foreach ($this->store->invitationsCreatedBy($member) as $invitation) {
                if (InvitationState::Usable === $invitation->stateAt($now) && $this->store->revokeInvitation($invitation->id, $now)) {
                    $events->record(new InvitationRevoked($invitation->organizationId->value, $invitation->id->value));
                }
            }

            $this->store->forgetInvitationCreator($member);
        });
    }

    /**
     * @return list<Invitation> the invitations the member created, the newest first
     */
    public function invitationsCreatedBy(MemberId $member): array
    {
        return $this->store->invitationsCreatedBy($member);
    }
}
