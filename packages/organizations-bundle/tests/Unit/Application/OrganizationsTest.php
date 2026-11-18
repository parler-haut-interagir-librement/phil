<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\EventRecorder;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Application\Exception\UnknownOrganization;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\SlugText;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\OrganizationArchived as OrganizationArchivedEvent;
use Phil\OrganizationsBundle\Domain\Event\OrganizationCreated;
use Phil\OrganizationsBundle\Domain\Event\OrganizationDomainChanged;
use Phil\OrganizationsBundle\Domain\Event\OrganizationRenamed;
use Phil\OrganizationsBundle\Domain\Event\OrganizationRestored;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Organizations::class)]
#[CoversClass(OrganizationAccess::class)]
#[CoversClass(SlugText::class)]
#[CoversClass(UnitOfWork::class)]
#[CoversClass(EventRecorder::class)]
#[CoversClass(SlugTaken::class)]
#[CoversClass(UnknownOrganization::class)]
#[CoversClass(PermissionDenied::class)]
#[CoversClass(NotAMember::class)]
#[CoversClass(OrganizationArchived::class)]
final class OrganizationsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testTheCreatorIsTheFirstOwner(): void
    {
        $acme = $this->stage->organizations->create('  Acme Guild ', Stage::member('ann'));

        self::assertSame('Acme Guild', $acme->name->value);
        self::assertSame('acme-guild', $acme->slug->value);
        self::assertSame(1_794_909_600, $acme->createdAt->getTimestamp());
        self::assertSame(['owner'], $this->stage->members->membership($acme->id, Stage::member('ann'))?->roles->names);
        self::assertSame(JoinMethod::Created, $this->stage->members->membership($acme->id, Stage::member('ann'))->method);
        self::assertEquals([new OrganizationCreated($acme->id->value, 'ann'), new MemberJoined($acme->id->value, 'ann', ['owner'], JoinMethod::Created)], $this->stage->events->events);
        self::assertEquals($acme, $this->stage->organizations->get($acme->id));
    }

    public function testAGivenIdentifierAndSlug(): void
    {
        $acme = $this->stage->organizations->create('Acme', Stage::member('ann'), 'the-acme', OrganizationId::fromString('acme-1'));

        self::assertSame('acme-1', $acme->id->value);
        self::assertSame('the-acme', $acme->slug->value);
    }

    public function testAGivenSlugIsCheckedNeverFixed(): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('"The-Acme" is not a slug of lowercase words joined by hyphens.');

        $this->stage->organizations->create('Acme', Stage::member('ann'), 'The-Acme');
    }

    public function testAGivenSlugThatIsTaken(): void
    {
        $this->stage->organizations->create('Acme', Stage::member('ann'), 'acme');

        try {
            $this->stage->organizations->create('Other', Stage::member('bob'), 'acme');
            self::fail('A taken slug was given twice.');
        } catch (SlugTaken $slugTaken) {
            self::assertSame('The slug "acme" is taken.', $slugTaken->getMessage());
        }

        self::assertCount(1, $this->stage->organizations->list());
        self::assertSame([], $this->stage->organizations->ofMember(Stage::member('bob')));
    }

    public function testAMadeSlugIsNumberedWhenTaken(): void
    {
        self::assertSame('acme', $this->stage->organizations->create('Acme', Stage::member('ann'))->slug->value);
        self::assertSame('acme-2', $this->stage->organizations->create('Acme', Stage::member('ann'))->slug->value);
        self::assertSame('acme-3', $this->stage->organizations->create('ACME', Stage::member('ann'))->slug->value);

        for ($i = 4; $i <= 20; ++$i) {
            self::assertSame('acme-'.$i, $this->stage->organizations->create('Acme', Stage::member('ann'))->slug->value);
        }

        $this->expectException(SlugTaken::class);
        $this->expectExceptionMessageIsOrContains('The slug "acme" is taken.');

        $this->stage->organizations->create('Acme', Stage::member('ann'));
    }

    public function testANumberedSlugStaysASlugAtFullLength(): void
    {
        $long = Slug::fromString(str_repeat('a', 250).'-bcde');

        self::assertSame(str_repeat('a', 250).'-2', SlugText::numbered($long, 2)->value);
        self::assertSame(str_repeat('a', 249).'-b-12', SlugText::numbered(Slug::fromString(str_repeat('a', 249).'-bcdef'), 12)->value);
        self::assertLessThanOrEqual(255, \strlen(SlugText::numbered(Slug::fromString(str_repeat('a', 255)), 20)->value));
    }

    public function testANameWithoutLettersNeedsASlug(): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageMatches('/Give the slug\.$/');

        $this->stage->organizations->create('!!!', Stage::member('ann'));
    }

    public function testFindByIdentifierOrSlug(): void
    {
        $acme = $this->stage->organizations->create('Acme', Stage::member('ann'), 'acme', OrganizationId::fromString('o-1'));

        self::assertSame('o-1', $this->stage->organizations->find('acme')->id->value);
        self::assertSame('o-1', $this->stage->organizations->find('o-1')->id->value);

        $other = $this->stage->organizations->create('Other', Stage::member('ann'), 'o-1');
        self::assertSame($acme->id->value, $this->stage->organizations->find('o-1')->id->value);
        self::assertSame($other->id->value, $this->stage->organizations->find($other->id->value)->id->value);

        foreach (['ghost', 'Not A Slug', 'O_2'] as $unknown) {
            try {
                $this->stage->organizations->find($unknown);
                self::fail('An unknown organization was found.');
            } catch (UnknownOrganization $unknownOrganization) {
                self::assertSame(\sprintf('No organization "%s".', $unknown), $unknownOrganization->getMessage());
            }
        }
    }

    public function testGetAnUnknownOrganization(): void
    {
        $this->expectException(UnknownOrganization::class);
        $this->expectExceptionMessageIsOrContains('No organization "ghost".');

        $this->stage->organizations->get(OrganizationId::fromString('ghost'));
    }

    public function testListAndOfMember(): void
    {
        $b = $this->stage->organizations->create('Bravo', Stage::member('ann'));
        $a = $this->stage->organizations->create('Alpha', Stage::member('bob'));
        $this->stage->organizations->archive($b->id, Stage::member('ann'));

        self::assertSame(['alpha', 'bravo'], array_map(static fn (Organization $o): string => $o->slug->value, $this->stage->organizations->list()));
        self::assertSame(['alpha'], array_map(static fn (Organization $o): string => $o->slug->value, $this->stage->organizations->list(OrganizationStatus::Active)));
        self::assertSame(['bravo'], array_map(static fn (Organization $o): string => $o->slug->value, $this->stage->organizations->list(OrganizationStatus::Archived)));
        self::assertSame(['bravo'], array_map(static fn (Organization $o): string => $o->slug->value, $this->stage->organizations->list(null, 10, 'alpha')));
        self::assertSame(['alpha'], array_map(static fn (Organization $o): string => $o->slug->value, $this->stage->organizations->list(null, 1)));
        self::assertSame([$a->id->value], array_map(static fn (Membership $m): string => $m->organizationId->value, $this->stage->organizations->ofMember(Stage::member('bob'))));
    }

    public function testRename(): void
    {
        $acme = $this->stage->acme();
        $renamed = $this->stage->organizations->rename($acme->id, Stage::member('bob'), 'Acme Builders');

        self::assertSame('Acme Builders', $renamed->name->value);
        self::assertSame('acme-guild', $renamed->slug->value);
        self::assertSame('acme-builders', $this->stage->organizations->rename($acme->id, Stage::member('bob'), 'Acme Builders', 'acme-builders')->slug->value);
        self::assertSame('acme-builders', $this->stage->organizations->rename($acme->id, Stage::member('bob'), 'Acme B.', 'acme-builders')->slug->value);
        self::assertEquals(array_fill(0, 3, new OrganizationRenamed($acme->id->value, 'bob')), $this->stage->events->events);
        self::assertSame('Acme B.', $this->stage->organizations->get($acme->id)->name->value);
    }

    public function testRenameToATakenSlug(): void
    {
        $acme = $this->stage->acme();
        $this->stage->organizations->create('Other', Stage::member('ann'), 'other');
        $this->stage->events->clear();

        try {
            $this->stage->organizations->rename($acme->id, Stage::member('ann'), 'Other', 'other');
            self::fail('A taken slug was taken.');
        } catch (SlugTaken) {
            self::assertSame('acme-guild', $this->stage->organizations->get($acme->id)->slug->value);
            self::assertSame([], $this->stage->events->events);
        }
    }

    public function testOnlyThoseWhoManageRename(): void
    {
        $acme = $this->stage->acme();

        try {
            $this->stage->organizations->rename($acme->id, Stage::member('cam'), 'Mine');
            self::fail('A member renamed the organization.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('The permission "organization.manage" is required.', $permissionDenied->getMessage());
        }

        try {
            $this->stage->organizations->rename($acme->id, Stage::member('dan'), 'Mine');
            self::fail('A stranger renamed the organization.');
        } catch (NotAMember $notaMember) {
            self::assertSame(\sprintf('Not a member of the organization "%s".', $acme->id->value), $notaMember->getMessage());
        }

        $this->expectException(UnknownOrganization::class);

        $this->stage->organizations->rename(OrganizationId::fromString('ghost'), Stage::member('ann'), 'Mine');
    }

    public function testArchiveAndRestore(): void
    {
        $acme = $this->stage->acme();

        try {
            $this->stage->organizations->archive($acme->id, Stage::member('bob'));
            self::fail('An admin archived the organization.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('The permission "organization.archive" is required.', $permissionDenied->getMessage());
        }

        $archived = $this->stage->organizations->archive($acme->id, Stage::member('ann'));
        self::assertFalse($archived->isActive());
        self::assertSame($this->stage->clock->now()->getTimestamp(), $archived->archivedAt?->getTimestamp());
        self::assertFalse($this->stage->organizations->get($acme->id)->isActive());

        $this->stage->organizations->archive($acme->id, Stage::member('ann'));
        self::assertEquals([new OrganizationArchivedEvent($acme->id->value, 'ann')], $this->stage->events->events);

        try {
            $this->stage->organizations->rename($acme->id, Stage::member('ann'), 'Mine');
            self::fail('An archived organization was renamed.');
        } catch (OrganizationArchived $organizationArchived) {
            self::assertSame(\sprintf('The organization "%s" is archived.', $acme->id->value), $organizationArchived->getMessage());
        }

        $this->stage->events->clear();
        self::assertTrue($this->stage->organizations->restore($acme->id, Stage::member('ann'))->isActive());
        self::assertTrue($this->stage->organizations->get($acme->id)->isActive());
        $this->stage->organizations->restore($acme->id, Stage::member('ann'));
        self::assertEquals([new OrganizationRestored($acme->id->value, 'ann')], $this->stage->events->events);
    }

    public function testTheDomain(): void
    {
        $acme = $this->stage->acme();

        self::assertSame('acme.org', $this->stage->organizations->useDomain($acme->id, Stage::member('bob'), 'ACME.org')->emailDomain?->value);
        self::assertSame('acme.org', $this->stage->organizations->get($acme->id)->emailDomain?->value);
        self::assertNull($this->stage->organizations->useDomain($acme->id, Stage::member('bob'), null)->emailDomain);
        self::assertNull($this->stage->organizations->get($acme->id)->emailDomain);
        self::assertEquals(array_fill(0, 2, new OrganizationDomainChanged($acme->id->value, 'bob')), $this->stage->events->events);

        $this->expectException(PermissionDenied::class);

        $this->stage->organizations->useDomain($acme->id, Stage::member('cam'), 'acme.org');
    }

    public function testTheAccessOfAMember(): void
    {
        $acme = $this->stage->acme();
        $access = $this->stage->access;

        self::assertTrue($access->can($acme->id, Stage::member('bob'), 'members.invite'));
        self::assertTrue($access->can($acme->id, Stage::member('bob'), 'billing.read'));
        self::assertFalse($access->can($acme->id, Stage::member('cam'), 'members.invite'));
        self::assertFalse($access->can($acme->id, Stage::member('dan'), 'organization.view'));
        self::assertFalse($access->can(OrganizationId::fromString('ghost'), Stage::member('ann'), 'organization.view'));
        self::assertTrue($access->hasRole($acme->id, Stage::member('ann'), 'owner'));
        self::assertFalse($access->hasRole($acme->id, Stage::member('bob'), 'owner'));
        self::assertFalse($access->hasRole($acme->id, Stage::member('dan'), 'member'));
        self::assertSame(['organization.view'], $access->permissions($acme->id, Stage::member('cam')));
        self::assertSame([], $access->permissions($acme->id, Stage::member('dan')));
        self::assertSame([], $access->permissions(OrganizationId::fromString('ghost'), Stage::member('ann')));
        self::assertSame($acme->id->value, $access->organization($acme->id)->id->value);

        $this->stage->organizations->archive($acme->id, Stage::member('ann'));

        self::assertSame(['organization.archive', 'organization.view'], $access->permissions($acme->id, Stage::member('ann')));
        self::assertSame(['organization.view'], $access->permissions($acme->id, Stage::member('bob')));
        self::assertTrue($access->can($acme->id, Stage::member('ann'), 'organization.archive'));
        self::assertTrue($access->can($acme->id, Stage::member('cam'), 'organization.view'));
        self::assertFalse($access->can($acme->id, Stage::member('ann'), 'members.invite'));

        $this->expectException(UnknownOrganization::class);

        $access->organization(OrganizationId::fromString('ghost'));
    }

    public function testEventsWaitForTheCommitAndARollbackSendsNone(): void
    {
        $recorded = $this->stage->work->run(function (EventRecorder $events): int {
            $events->record(new OrganizationRenamed('o', 'a'), new OrganizationRenamed('o', 'b'));
            self::assertSame([], $this->stage->events->events);

            return 7;
        });

        self::assertSame(7, $recorded);
        self::assertEquals([new OrganizationRenamed('o', 'a'), new OrganizationRenamed('o', 'b')], $this->stage->events->events);

        $this->stage->events->clear();

        try {
            $this->stage->work->run(static function (EventRecorder $events): never {
                $events->record(new OrganizationRenamed('o', 'a'));

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->stage->events->events);
        self::assertSame(3, new UnitOfWork($this->stage->store)->run(static fn (EventRecorder $events): int => 3));
    }
}
