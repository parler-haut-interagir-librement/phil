<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Fixtures;

use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\DomainJoin;
use Phil\OrganizationsBundle\Application\InvitationPolicy;
use Phil\OrganizationsBundle\Application\Invitations;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Application\OnBehalf;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\Teams;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Clock\MockClock;

/**
 * The services of the bundle on one store, a clock that stands still and a recording dispatcher.
 */
final class Stage
{
    public const string KEY = 'a-key-of-thirty-two-bytes-at-least-for-tests';

    public readonly MockClock $clock;

    public readonly RecordingDispatcher $events;

    public readonly RoleCatalog $roles;

    public readonly RoleCatalog $teamRoles;

    public readonly OrganizationAccess $access;

    public readonly UnitOfWork $work;

    public readonly Organizations $organizations;

    public readonly Members $members;

    public readonly Teams $teams;

    public readonly Invitations $invitations;

    public readonly OnBehalf $onBehalf;

    public readonly OwnershipCheck $ownership;

    public readonly MemberErasure $erasure;

    public readonly UnitStore $unitStore;

    public readonly RoleCatalog $unitRoles;

    public readonly UnitAccess $unitAccess;

    public readonly Units $units;

    public readonly UnitTree $tree;

    public function __construct(
        public readonly OrganizationStore $store = new InMemoryOrganizationStore(),
        ?RoleCatalog $roles = null,
        ?RoleCatalog $teamRoles = null,
        InvitationPolicy $policy = new InvitationPolicy(),
        ?RoleCatalog $unitRoles = null,
        bool $singleMembership = false,
    ) {
        $this->clock = new MockClock('2026-11-17 10:00:00 UTC');
        $this->events = new RecordingDispatcher();
        $this->roles = $roles ?? RoleCatalog::organization(['billing' => ['billing.read'], 'admin' => ['billing.read']]);
        $this->teamRoles = $teamRoles ?? RoleCatalog::team(['reviewer' => ['review.approve']]);
        $this->access = new OrganizationAccess($store, $this->roles);
        $this->work = new UnitOfWork($store, $this->events);
        $slugger = new TransliteratingSlugger();
        $this->organizations = new Organizations($store, $this->work, $this->access, $slugger, $this->clock);
        $units = $store instanceof UnitStore ? $store : null;
        $this->unitStore = $units ?? new InMemoryOrganizationStore();
        $this->unitRoles = $unitRoles ?? RoleCatalog::unit(['instructor' => ['courses.assign'], 'manager' => ['reports.read']]);
        $this->unitAccess = new UnitAccess($store, $this->unitStore, $this->access, $this->unitRoles);
        $this->units = new Units($store, $this->unitStore, $this->work, $this->access, $this->unitAccess, $this->clock, $singleMembership);
        $this->tree = new UnitTree($this->unitStore);
        $this->members = new Members($store, $this->work, $this->access, $this->clock, $units);
        $this->teams = new Teams($store, $this->work, $this->access, $this->teamRoles, $slugger, $this->clock);
        $this->invitations = new Invitations($store, $this->work, $this->access, new TokenHasher(self::KEY), $this->clock, $policy);
        $this->onBehalf = new OnBehalf($this->access, $this->clock, $this->events);
        $this->ownership = new OwnershipCheck($store);
        $this->erasure = new MemberErasure($store, $this->work, $this->clock, $units);
    }

    public function domainJoin(bool $enabled = true): DomainJoin
    {
        return new DomainJoin($this->store, $this->work, $this->access, $this->clock, $enabled);
    }

    public static function member(string $id): MemberId
    {
        return MemberId::fromString($id);
    }

    /**
     * An organization owned by `ann`, with `bob` as admin and `cam` as member.
     */
    public function acme(): Organization
    {
        $acme = $this->organizations->create('Acme Guild', self::member('ann'));
        $this->members->add($acme->id, self::member('ann'), self::member('bob'), ['admin']);
        $this->members->add($acme->id, self::member('ann'), self::member('cam'), ['member']);
        $this->events->clear();

        return $acme;
    }
}
