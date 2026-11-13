<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Application;

use Override;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Edges the other suites leave: viewing as another member, a missing logger, default limits, what is read at most
 * once or ten times, and names that end with a line break.
 */
#[CoversClass(AccessSubject::class)]
#[CoversClass(ItemAccess::class)]
#[CoversClass(ViewAs::class)]
#[CoversClass(Items::class)]
#[CoversClass(Sharing::class)]
#[CoversClass(AccessGroups::class)]
#[CoversClass(AccessGroup::class)]
#[CoversClass(SharingLevels::class)]
final class AccessEdgesTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->spaces->addMember(MemberId::of('ann'));
    }

    public function testTheMemberViewedComesBeforeTheMemberSignedIn(): void
    {
        $this->stage->member->signIn('ada');
        $this->stage->viewAsStore->set(MemberId::of('ann'));

        self::assertSame('member:ann', $this->stage->subject->subject()->toString());
        self::assertTrue($this->stage->subject->isViewingAs());
    }

    public function testWithoutViewAsNobodyViewsAsAnother(): void
    {
        $subject = new AccessSubject($this->stage->member);

        self::assertFalse($subject->isViewingAs());
        self::assertSame('visitor:anonymous', $subject->subject()->toString());
    }

    public function testViewingAsMemberReadsAndNeverActs(): void
    {
        $this->stage->items->register(new Page('1'), MemberId::of('ann'), Visibility::Owner);
        $this->stage->member->signIn('ada');
        $this->stage->viewAsStore->set(MemberId::of('ann'));

        self::assertTrue($this->stage->access->can('view', new Page('1')));
        self::assertFalse($this->stage->access->can('manage', new Page('1')));
        self::assertSame(['1'], $this->stage->access->visible('page')->ids());
        self::assertSame([], $this->stage->access->visible('page', 100, 'manage')->ids());
        self::assertTrue($this->stage->access->visible('page', 100, 'manage')->complete);
    }

    public function testALevelOfTheSiteIsAskedFor(): void
    {
        $this->expectException(InvalidAccess::class);

        $this->stage->access->can('own', new Page('1'));
    }

    public function testALevelOfTheSiteIsAskedForWhileViewingAsToo(): void
    {
        $this->stage->member->signIn('ada');
        $this->stage->viewAsStore->set(MemberId::of('ann'));

        $this->expectException(InvalidAccess::class);

        $this->stage->access->can('own', new Page('1'));
    }

    public function testWithoutALoggerAnExhaustedBudgetIsStillRefused(): void
    {
        $stage = new Stage(new Budget(maxReads: 1));
        $stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Members);
        $stage->member->signIn('olga');

        $access = new ItemAccess($stage->checker, $stage->subject, $stage->levels);

        self::assertFalse($access->can('view', new Page('1')));
        self::assertFalse($access->visible('page')->complete);

        $stage->viewAsStore->set(MemberId::of('ann'));
        $viewAs = new ViewAs($stage->viewAsStore, $stage->member, $stage->spaces);

        self::assertNotInstanceOf(MemberId::class, $viewAs->member());
    }

    public function testAListingStopsAtOneHundredByDefault(): void
    {
        $this->writeTuples(101, static fn (int $i): RelationTuple => new RelationTuple(ResourceReference::of('page', 'p'.$i), 'view', Subject::fromString('member:ann')));
        $this->stage->member->signIn('ann');

        self::assertCount(100, $this->stage->access->visible('page')->ids());
    }

    public function testTheGroupsAndMembersListedStopAtOneHundredByDefault(): void
    {
        $this->writeTuples(101, static fn (int $i): RelationTuple => new RelationTuple(ResourceReference::of('group', 'g'.$i), 'member', Subject::fromString('member:ann')));
        $this->writeTuples(101, static fn (int $i): RelationTuple => new RelationTuple(ResourceReference::of('group', 'supporters'), 'member', Subject::fromString('member:m'.$i)));

        self::assertCount(100, $this->stage->groups->groupsOf(MemberId::of('ann')));
        self::assertCount(100, $this->stage->groups->members('supporters'));
    }

    public function testEveryGroupOpenToTheDefaultIsJoined(): void
    {
        $groups = new AccessGroups($this->stage->writer, $this->stage->store, $this->stage->clock, [
            AccessGroup::of('first', [EntryPoint::Default]),
            AccessGroup::of('second', [EntryPoint::Default]),
            AccessGroup::of('third', [EntryPoint::Purchase]),
        ]);

        self::assertSame(['first', 'second'], $groups->joinDefaults(MemberId::of('ann')));
    }

    public function testOnlyTenTuplesOfAnOwnerAreReplaced(): void
    {
        $page = ResourceReference::of('page', '1');
        $this->writeTuples(11, static fn (int $i): RelationTuple => new RelationTuple($page, 'owner', Subject::fromString('member:o'.$i)));

        $this->stage->items->register($page, MemberId::of('olga'));

        self::assertCount(2, $this->stage->store->subjects($page, 'owner', 100));
    }

    public function testATupleOfAnotherKindIsNoShare(): void
    {
        $page = ResourceReference::of('page', '1');
        $this->stage->store->update([], [new RelationTuple($page, 'view', Subject::fromString('visitor:anonymous'))]);

        self::assertSame([], $this->stage->sharing->shares($page));
    }

    public function testNamesEndingWithALineBreakAreRefused(): void
    {
        foreach (["ab\n", 'a b', 'Ab', '1a', str_repeat('a', 65)] as $name) {
            try {
                AccessGroup::of($name, [EntryPoint::Default]);
                self::fail(\sprintf('"%s" should be refused.', $name));
            } catch (InvalidAccess) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidAccess::class);

        SharingLevels::of(["read\n"]);
    }

    /**
     * @param callable(int): RelationTuple $make
     */
    private function writeTuples(int $count, callable $make): void
    {
        $this->stage->store->update([], array_map($make, range(100, 99 + $count)));
    }
}
