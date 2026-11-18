<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Application\Exception\UnknownOrganization;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\OrganizationArchived;
use Phil\OrganizationsBundle\Domain\Event\OrganizationCreated;
use Phil\OrganizationsBundle\Domain\Event\OrganizationDomainChanged;
use Phil\OrganizationsBundle\Domain\Event\OrganizationRenamed;
use Phil\OrganizationsBundle\Domain\Event\OrganizationRestored;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Psr\Clock\ClockInterface;

/**
 * Creates organizations and changes them: a name, a slug — made from the name by the slugger when none is given —,
 * archived and restored, a verified email domain. The member who creates an organization is its first owner.
 */
final readonly class Organizations
{
    /** The suffixes tried, "-2" to "-20", when the slug made from a name is taken. */
    public const int MAX_SUFFIX = 20;

    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private Slugger $slugger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string|null $slug the slug, checked and never fixed; made from the name when null, with a numbered suffix
     *                          when it is taken
     *
     * @throws InvalidOrganization when the name or the slug is out of shape
     * @throws SlugTaken           when the slug given, or every numbered slug made from the name, is taken
     */
    public function create(string $name, MemberId $owner, ?string $slug = null, ?OrganizationId $id = null): Organization
    {
        $organizationName = OrganizationName::fromString($name);

        return $this->work->run(function (EventRecorder $events) use ($organizationName, $owner, $slug, $id): Organization {
            $now = $this->clock->now();
            $organization = Organization::create($id ?? OrganizationId::generate(), $organizationName, $this->freeSlug($organizationName, $slug), $now);
            $roles = Roles::of([RoleCatalog::OWNER]);

            $this->store->saveOrganization($organization);
            $this->store->saveMembership(Membership::join($organization->id, $owner, $roles, JoinMethod::Created, $now));

            $events->record(
                new OrganizationCreated($organization->id->value, $owner->value),
                new MemberJoined($organization->id->value, $owner->value, $roles->names, JoinMethod::Created),
            );

            return $organization;
        });
    }

    /**
     * @throws UnknownOrganization when there is none
     */
    public function get(OrganizationId $id): Organization
    {
        return $this->store->organization($id) ?? throw UnknownOrganization::named($id->value);
    }

    /**
     * The organization of an identifier or of a slug.
     *
     * @throws UnknownOrganization when there is none
     */
    public function find(string $idOrSlug): Organization
    {
        try {
            $bySlug = $this->store->organizationBySlug(Slug::fromString($idOrSlug));
        } catch (InvalidSlug) {
            $bySlug = null;
        }

        try {
            $byId = $this->store->organization(OrganizationId::fromString($idOrSlug));
        } catch (InvalidOrganization) {
            $byId = null;
        }

        return $byId ?? $bySlug ?? throw UnknownOrganization::named($idOrSlug);
    }

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<Organization> sorted by slug
     */
    public function list(?OrganizationStatus $status = null, int $limit = 100, ?string $afterSlug = null): array
    {
        return $this->store->organizations($status, $limit, $afterSlug);
    }

    /**
     * @return list<Membership> the member's memberships, sorted by organization
     */
    public function ofMember(MemberId $member): array
    {
        return $this->store->membershipsOf($member);
    }

    /**
     * Renames the organization, and changes its slug when one is given; the actor holds `organization.manage`.
     */
    public function rename(OrganizationId $id, MemberId $actor, string $name, ?string $slug = null): Organization
    {
        $organizationName = OrganizationName::fromString($name);
        $newSlug = null === $slug ? null : SlugText::given($slug);

        return $this->work->run(function (EventRecorder $events) use ($id, $actor, $organizationName, $newSlug): Organization {
            $organization = $this->access->lock($id);
            $this->access->require($organization, $actor, Permission::MANAGE);

            if ($newSlug instanceof Slug && !$newSlug->equals($organization->slug) && $this->store->organizationBySlug($newSlug) instanceof Organization) {
                throw SlugTaken::of($newSlug->value);
            }

            $organization->rename($organizationName, $newSlug);
            $this->store->saveOrganization($organization);
            $events->record(new OrganizationRenamed($id->value, $actor->value));

            return $organization;
        });
    }

    /**
     * Archives the organization, read only until it is restored; the actor holds `organization.archive`.
     */
    public function archive(OrganizationId $id, MemberId $actor): Organization
    {
        return $this->work->run(function (EventRecorder $events) use ($id, $actor): Organization {
            $organization = $this->access->lock($id);
            $this->access->require($organization, $actor, Permission::ARCHIVE);

            if ($organization->isActive()) {
                $organization->archive($this->clock->now());
                $this->store->saveOrganization($organization);
                $events->record(new OrganizationArchived($id->value, $actor->value));
            }

            return $organization;
        });
    }

    /**
     * Restores an archived organization; the actor holds `organization.archive`.
     */
    public function restore(OrganizationId $id, MemberId $actor): Organization
    {
        return $this->work->run(function (EventRecorder $events) use ($id, $actor): Organization {
            $organization = $this->access->lock($id);
            $this->access->require($organization, $actor, Permission::ARCHIVE);

            if (!$organization->isActive()) {
                $organization->restore();
                $this->store->saveOrganization($organization);
                $events->record(new OrganizationRestored($id->value, $actor->value));
            }

            return $organization;
        });
    }

    /**
     * Records the email domain the organization verified, or removes it with null; the actor holds
     * `organization.manage`. Verifying the domain — a DNS record, a message to an address of the domain — is the
     * application's, before this call.
     */
    public function useDomain(OrganizationId $id, MemberId $actor, ?string $domain): Organization
    {
        $emailDomain = null === $domain ? null : EmailDomain::fromString($domain);

        return $this->work->run(function (EventRecorder $events) use ($id, $actor, $emailDomain): Organization {
            $organization = $this->access->lock($id);
            $this->access->require($organization, $actor, Permission::MANAGE);
            $organization->useDomain($emailDomain);
            $this->store->saveOrganization($organization);
            $events->record(new OrganizationDomainChanged($id->value, $actor->value));

            return $organization;
        });
    }

    private function freeSlug(OrganizationName $name, ?string $slug): Slug
    {
        if (null !== $slug) {
            $given = SlugText::given($slug);

            return $this->store->organizationBySlug($given) instanceof Organization ? throw SlugTaken::of($given->value) : $given;
        }

        $made = SlugText::made($this->slugger, $name->value);

        for ($suffix = 1; $suffix <= self::MAX_SUFFIX; ++$suffix) {
            $candidate = 1 === $suffix ? $made : SlugText::numbered($made, $suffix);

            if (!$this->store->organizationBySlug($candidate) instanceof Organization) {
                return $candidate;
            }
        }

        throw SlugTaken::of($made->value);
    }
}
