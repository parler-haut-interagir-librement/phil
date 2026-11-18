<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\DomainJoinRefused;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Joining an organization by its verified email domain, off unless the configuration turns it on: a member whose
 * address the application has verified, in the domain an organization verified, joins it with the configured roles,
 * `member` by default and never `owner`.
 *
 * The address is read for its domain and never kept, logged or dispatched.
 */
final readonly class DomainJoin
{
    private Roles $roles;

    /**
     * @param list<string> $roles the roles a member joins with
     *
     * @throws InvalidOrganization when the roles name `owner`, or are out of shape
     */
    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private ClockInterface $clock,
        private bool $enabled = false,
        array $roles = [RoleCatalog::MEMBER],
    ) {
        $this->roles = $access->roles()->check(Roles::of($roles));

        if ($this->roles->has(RoleCatalog::OWNER)) {
            throw InvalidOrganization::because('Joining by a verified domain never grants the owner role.');
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<Organization> the active organizations whose verified domain is the address's, none when joining by
     *                            the domain is off
     *
     * @throws InvalidOrganization when the address has no domain of the right shape
     */
    public function organizationsFor(#[SensitiveParameter] string $verifiedAddress): array
    {
        return $this->enabled ? $this->store->organizationsWithDomain(EmailDomain::ofAddress($verifiedAddress)) : [];
    }

    /**
     * @param string $verifiedAddress an address of the member that the application has verified
     *
     * @throws DomainJoinRefused    when joining by the domain is off, or the address is not in the organization's domain
     * @throws OrganizationArchived when the organization is archived
     * @throws AlreadyMember        when the member is already a member
     */
    public function join(OrganizationId $organizationId, MemberId $member, #[SensitiveParameter] string $verifiedAddress): Membership
    {
        if (!$this->enabled) {
            throw DomainJoinRefused::disabled();
        }

        $domain = EmailDomain::ofAddress($verifiedAddress);

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $member, $domain): Membership {
            $organization = $this->access->lock($organizationId);

            if (!$organization->emailDomain instanceof EmailDomain || !$organization->emailDomain->equals($domain)) {
                throw DomainJoinRefused::notInDomain();
            }

            if (!$organization->isActive()) {
                throw OrganizationArchived::of($organizationId->value);
            }

            if ($this->store->membership($organizationId, $member) instanceof Membership) {
                throw AlreadyMember::ofOrganization($organizationId->value);
            }

            $membership = Membership::join($organizationId, $member, $this->roles, JoinMethod::Domain, $this->clock->now());
            $this->store->saveMembership($membership);
            $events->record(new MemberJoined($organizationId->value, $member->value, $this->roles->names, JoinMethod::Domain));

            return $membership;
        });
    }
}
