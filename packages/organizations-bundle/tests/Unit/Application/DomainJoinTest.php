<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\DomainJoin;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\DomainJoinRefused;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainJoin::class)]
#[CoversClass(DomainJoinRefused::class)]
final class DomainJoinTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->stage->organizations->useDomain($this->acme->id, Stage::member('ann'), 'acme.org');
        $this->stage->events->clear();
    }

    public function testOffByDefault(): void
    {
        $join = new DomainJoin($this->stage->store, $this->stage->work, $this->stage->access, $this->stage->clock);

        self::assertFalse($join->isEnabled());
        self::assertSame([], $join->organizationsFor('dan@acme.org'));

        try {
            $join->join($this->acme->id, Stage::member('dan'), 'dan@acme.org');
            self::fail('A member joined by the domain while it is off.');
        } catch (DomainJoinRefused $domainJoinRefused) {
            self::assertSame('Joining by a verified email domain is off.', $domainJoinRefused->getMessage());
        }

        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('dan')));
    }

    public function testJoin(): void
    {
        $join = $this->stage->domainJoin();
        $other = $this->stage->organizations->create('Other', Stage::member('ann'));
        $this->stage->organizations->useDomain($other->id, Stage::member('ann'), 'acme.org');
        $archived = $this->stage->organizations->create('Archived', Stage::member('ann'));
        $this->stage->organizations->useDomain($archived->id, Stage::member('ann'), 'acme.org');
        $this->stage->organizations->archive($archived->id, Stage::member('ann'));
        $this->stage->events->clear();

        self::assertTrue($join->isEnabled());
        self::assertSame(['acme-guild', 'other'], array_map(static fn (Organization $o): string => $o->slug->value, $join->organizationsFor('Dan@ACME.org')));
        self::assertSame([], $join->organizationsFor('dan@elsewhere.org'));

        $membership = $join->join($this->acme->id, Stage::member('dan'), 'dan@Acme.Org');

        self::assertSame(['member'], $membership->roles->names);
        self::assertSame(JoinMethod::Domain, $membership->method);
        self::assertSame(['member'], $this->stage->members->membership($this->acme->id, Stage::member('dan'))?->roles->names);
        self::assertEquals([new MemberJoined($this->acme->id->value, 'dan', ['member'], JoinMethod::Domain)], $this->stage->events->events);

        try {
            $join->join($this->acme->id, Stage::member('dan'), 'dan@acme.org');
            self::fail('A member joined twice.');
        } catch (AlreadyMember) {
        }

        try {
            $join->join($archived->id, Stage::member('dan'), 'dan@acme.org');
            self::fail('A member joined an archived organization.');
        } catch (OrganizationArchived) {
        }
    }

    public function testTheAddressMustBeInTheDomain(): void
    {
        $join = $this->stage->domainJoin();
        $without = $this->stage->organizations->create('Without', Stage::member('ann'));

        foreach ([[$this->acme, 'dan@sub.acme.org'], [$this->acme, 'dan@acme.org.evil.example'], [$without, 'dan@acme.org']] as [$organization, $address]) {
            try {
                $join->join($organization->id, Stage::member('dan'), $address);
                self::fail('An address outside the domain joined.');
            } catch (DomainJoinRefused $domainJoinRefused) {
                self::assertSame('The address is not in the verified domain of the organization.', $domainJoinRefused->getMessage());
            }
        }

        $this->expectException(InvalidOrganization::class);

        $join->join($this->acme->id, Stage::member('dan'), 'not an address');
    }

    public function testConfiguredRoles(): void
    {
        $join = new DomainJoin($this->stage->store, $this->stage->work, $this->stage->access, $this->stage->clock, true, ['member', 'billing']);

        self::assertSame(['billing', 'member'], $join->join($this->acme->id, Stage::member('dan'), 'dan@acme.org')->roles->names);
    }

    public function testNeverTheOwnerRoleNorAnUndeclaredOne(): void
    {
        try {
            new DomainJoin($this->stage->store, $this->stage->work, $this->stage->access, $this->stage->clock, true, ['member', 'owner']);
            self::fail('The owner role was granted by the domain.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertSame('Joining by a verified domain never grants the owner role.', $invalidOrganization->getMessage());
        }

        $this->expectException(UnknownRole::class);

        new DomainJoin($this->stage->store, $this->stage->work, $this->stage->access, $this->stage->clock, true, ['ghost']);
    }
}
