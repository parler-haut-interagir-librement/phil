<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;

/**
 * An invitation to join an organization with roles: usable until it expires, as many times as it allows, unless it is
 * revoked. Its token is never kept, only the keyed hash of it; the member who created it is kept, so that an
 * invitation never grants more than its creator may still grant.
 */
final class Invitation
{
    public const int MAX_USES = 10_000;

    private function __construct(
        public readonly InvitationId $id,
        public readonly OrganizationId $organizationId,
        public readonly Roles $roles,
        public readonly int $maxUses,
        public private(set) int $uses,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $expiresAt,
        public private(set) ?MemberId $createdBy,
        public private(set) ?DateTimeImmutable $revokedAt,
    ) {
    }

    /**
     * @throws InvalidOrganization when the uses are out of range or the invitation would expire before it is created
     */
    public static function create(InvitationId $id, OrganizationId $organizationId, Roles $roles, int $maxUses, DateTimeImmutable $now, DateTimeImmutable $expiresAt, MemberId $createdBy): self
    {
        if ($maxUses < 1 || $maxUses > self::MAX_USES) {
            throw InvalidOrganization::because(\sprintf('An invitation is used 1 to %d times.', self::MAX_USES));
        }

        if ($expiresAt->getTimestamp() <= $now->getTimestamp()) {
            throw InvalidOrganization::because('An invitation expires after it is created.');
        }

        return new self($id, $organizationId, $roles, $maxUses, 0, Moment::of($now), Moment::of($expiresAt), $createdBy, null);
    }

    /**
     * An invitation read back from a store.
     */
    public static function restored(InvitationId $id, OrganizationId $organizationId, Roles $roles, int $maxUses, int $uses, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt, ?MemberId $createdBy, ?DateTimeImmutable $revokedAt): self
    {
        return new self($id, $organizationId, $roles, $maxUses, $uses, $createdAt, $expiresAt, $createdBy, $revokedAt);
    }

    public function stateAt(DateTimeImmutable $now): InvitationState
    {
        return match (true) {
            $this->revokedAt instanceof DateTimeImmutable => InvitationState::Revoked,
            $this->uses >= $this->maxUses => InvitationState::UsedUp,
            $this->expiresAt->getTimestamp() <= $now->getTimestamp() => InvitationState::Expired,
            default => InvitationState::Usable,
        };
    }

    /**
     * Counts one use; a store does the same in one conditional write.
     */
    public function use(): void
    {
        ++$this->uses;
    }

    public function revoke(DateTimeImmutable $now): void
    {
        $this->revokedAt ??= Moment::of($now);
    }

    /**
     * Forgets who created the invitation, when their personal data is erased.
     */
    public function forgetCreator(): void
    {
        $this->createdBy = null;
    }
}
