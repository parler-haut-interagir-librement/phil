<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use DateTimeImmutable;
use Phil\AccessLinks\Exception\InvalidToken;
use Phil\AccessLinks\LinkToken;
use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\InvitationNotUsable;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnknownInvitation;
use Phil\OrganizationsBundle\Domain\Event\InvitationAccepted;
use Phil\OrganizationsBundle\Domain\Event\InvitationCreated;
use Phil\OrganizationsBundle\Domain\Event\InvitationRevoked;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use SensitiveParameter;

/**
 * Invitations to join an organization with roles.
 *
 * The token is 256 bits of a secure generator, written as the tokens of `phil/access-links`, and kept only as its
 * keyed hash, under keys that rotate: a copy of the table opens nothing. An invitation lasts until its expiry, for as
 * many uses as it allows; each use is counted by one conditional write whose row count is checked, so that two members
 * accepting the last use at once never both join. A member who invites holds `members.invite` and grants only roles
 * whose permissions they hold; when the invitation is accepted, its creator must still be a member who may grant its
 * roles, or it is refused.
 */
final readonly class Invitations
{
    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private TokenHasher $hasher,
        private ClockInterface $clock,
        private InvitationPolicy $policy = new InvitationPolicy(),
        private Randomizer $randomizer = new Randomizer(),
    ) {
    }

    /**
     * @param iterable<string>       $roles     the roles the invitation grants
     * @param DateTimeImmutable|null $expiresAt the default lifetime from now when null, at most the longest lifetime
     * @param int|null               $maxUses   the default uses when null, at most the most uses
     *
     * @throws InvalidOrganization when the expiry or the uses are out of the policy
     * @throws PermissionDenied    when the actor may not invite, or not grant these roles
     */
    public function create(OrganizationId $organizationId, MemberId $actor, iterable $roles, ?DateTimeImmutable $expiresAt = null, ?int $maxUses = null): IssuedInvitation
    {
        $granted = $this->access->roles()->check(Roles::of($roles));
        $uses = $maxUses ?? $this->policy->defaultUses;

        if ($uses < 1 || $uses > $this->policy->maxUses) {
            throw InvalidOrganization::because(\sprintf('An invitation is used 1 to %d times.', $this->policy->maxUses));
        }

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $granted, $expiresAt, $uses): IssuedInvitation {
            $now = $this->clock->now();
            $expiry = $expiresAt ?? $now->modify(\sprintf('+%d seconds', $this->policy->defaultLifetime));

            if ($expiry->getTimestamp() > $now->getTimestamp() + $this->policy->maxLifetime) {
                throw InvalidOrganization::because(\sprintf('An invitation lasts at most %d seconds.', $this->policy->maxLifetime));
            }

            $organization = $this->access->lock($organizationId);
            $membership = $this->access->require($organization, $actor, Permission::INVITE);

            if (!$this->access->roles()->covers($membership->roles, $granted)) {
                throw PermissionDenied::widerRoles();
            }

            $token = LinkToken::generate($this->randomizer);
            $invitation = Invitation::create(InvitationId::generate(), $organizationId, $granted, $uses, $now, $expiry, $actor);
            $this->store->addInvitation($invitation, $this->hasher->hash($token));
            $events->record(new InvitationCreated($organizationId->value, $invitation->id->value, $actor->value));

            return new IssuedInvitation($invitation, $token);
        });
    }

    /**
     * The invitation of a token, when it can still be used: to show what it grants before the member accepts.
     *
     * @throws InvitationNotUsable when the token is malformed or unknown, or the invitation cannot be used
     */
    public function preview(#[SensitiveParameter] string $token): Invitation
    {
        $invitation = $this->find($token);
        $organization = $this->store->organization($invitation->organizationId) ?? throw InvitationNotUsable::because(InvitationNotUsable::UNKNOWN);
        $this->usable($invitation, $organization, $this->clock->now());

        return $invitation;
    }

    /**
     * The member joins the organization with the invitation's roles, using it once.
     *
     * @throws InvitationNotUsable when the token is malformed or unknown, the invitation is expired, revoked or used
     *                             up, the organization is archived, or its creator may no longer grant its roles
     * @throws AlreadyMember       when the member is already a member; the invitation is not used
     */
    public function accept(#[SensitiveParameter] string $token, MemberId $member): Membership
    {
        $invitation = $this->find($token);

        return $this->work->run(function (EventRecorder $events) use ($invitation, $member): Membership {
            $now = $this->clock->now();
            $organization = $this->access->lock($invitation->organizationId);

            $this->usable($invitation, $organization, $now);

            if ($this->store->membership($organization->id, $member) instanceof Membership) {
                throw AlreadyMember::ofOrganization($organization->id->value);
            }

            if (!$this->store->useInvitation($invitation->id, $now)) {
                throw InvitationNotUsable::because(InvitationNotUsable::USED_UP);
            }

            $membership = Membership::join($organization->id, $member, $invitation->roles, JoinMethod::Invitation, $now);
            $this->store->saveMembership($membership);
            $events->record(
                new InvitationAccepted($organization->id->value, $invitation->id->value, $member->value),
                new MemberJoined($organization->id->value, $member->value, $invitation->roles->names, JoinMethod::Invitation),
            );

            return $membership;
        });
    }

    /**
     * @throws UnknownInvitation when the organization has no such invitation
     */
    public function revoke(OrganizationId $organizationId, InvitationId $id, MemberId $actor): void
    {
        $this->work->run(function (EventRecorder $events) use ($organizationId, $id, $actor): void {
            $organization = $this->access->lock($organizationId);
            $this->access->require($organization, $actor, Permission::INVITE);
            $invitation = $this->store->invitation($id);

            if (!$invitation instanceof Invitation || !$invitation->organizationId->equals($organizationId)) {
                throw UnknownInvitation::named($id->value);
            }

            if ($this->store->revokeInvitation($id, $this->clock->now())) {
                $events->record(new InvitationRevoked($organizationId->value, $id->value, $actor->value));
            }
        });
    }

    /**
     * @return list<Invitation> the organization's invitations, the newest first
     */
    public function invitations(OrganizationId $organizationId): array
    {
        return $this->store->invitations($organizationId);
    }

    /**
     * Deletes the invitations that expired or were revoked more than `$retention` seconds ago.
     *
     * @param non-negative-int $retention
     *
     * @return int how many
     */
    public function purge(int $retention): int
    {
        return $this->store->purgeInvitations($this->clock->now()->modify(\sprintf('-%d seconds', $retention)));
    }

    /**
     * @throws InvitationNotUsable when the token is malformed or unknown
     */
    private function find(#[SensitiveParameter] string $token): Invitation
    {
        try {
            $parsed = LinkToken::parse($token);
        } catch (InvalidToken) {
            throw InvitationNotUsable::because(InvitationNotUsable::UNKNOWN);
        }

        return $this->store->invitationByHash($this->hasher->candidates($parsed)) ?? throw InvitationNotUsable::because(InvitationNotUsable::UNKNOWN);
    }

    /**
     * @throws InvitationNotUsable when the invitation cannot be used at that moment, or its creator may no longer grant
     *                             its roles
     */
    private function usable(Invitation $invitation, Organization $organization, DateTimeImmutable $now): void
    {
        if (!$organization->isActive()) {
            throw InvitationNotUsable::because(InvitationNotUsable::ARCHIVED);
        }

        $state = $invitation->stateAt($now);

        if (InvitationState::Usable !== $state) {
            throw InvitationNotUsable::because(match ($state) {
                InvitationState::Expired => InvitationNotUsable::EXPIRED, InvitationState::Revoked => InvitationNotUsable::REVOKED, InvitationState::UsedUp => InvitationNotUsable::USED_UP,
            });
        }

        $creator = $invitation->createdBy instanceof MemberId ? $this->store->membership($invitation->organizationId, $invitation->createdBy) : null;
        $roles = $this->access->roles();

        if (!$creator instanceof Membership || !$roles->grants($creator->roles, Permission::INVITE) || !$roles->covers($creator->roles, $invitation->roles)) {
            throw InvitationNotUsable::because(InvitationNotUsable::CREATOR);
        }
    }
}
