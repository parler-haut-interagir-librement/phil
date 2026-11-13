<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\Event\MemberJoinedGroup;
use Phil\AccessBundle\Application\Event\MemberLeftGroup;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\View\Membership;
use Phil\AccessBundle\Application\View\Share;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sharing::class)]
#[CoversClass(AccessGroups::class)]
#[CoversClass(Share::class)]
#[CoversClass(Membership::class)]
#[CoversClass(MemberJoinedGroup::class)]
#[CoversClass(MemberLeftGroup::class)]
final class SharingAndGroupsTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addMember(MemberId::of('ann'));
        $this->stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Members);
    }

    public function testAShareGivesItsLevelAndTheNarrowerOnes(): void
    {
        $this->stage->sharing->share(new Page('1'), 'comment', MemberId::of('bob'));

        self::assertTrue($this->can('bob', 'comment'));
        self::assertTrue($this->can('bob', 'view'));
        self::assertFalse($this->can('bob', 'edit'));

        $this->stage->sharing->unshare(new Page('1'), 'comment', MemberId::of('bob'));
        self::assertFalse($this->can('bob', 'view'));
    }

    public function testAShareEndsOnItsOwn(): void
    {
        $until = $this->stage->clock->now()->modify('+2 hours');
        $this->stage->sharing->share(new Page('1'), 'edit', MemberId::of('bob'), $until);

        self::assertTrue($this->can('bob', 'edit'));
        $this->stage->clock->sleep(7199);
        self::assertTrue($this->can('bob', 'edit'));
        $this->stage->clock->sleep(1);
        self::assertFalse($this->can('bob', 'edit'));
    }

    public function testAShareEndsAfterNow(): void
    {
        $this->stage->sharing->share(new Page('1'), 'edit', MemberId::of('bob'), $this->stage->clock->now()->modify('+1 second'));

        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('A share ends after now.');

        $this->stage->sharing->share(new Page('1'), 'edit', MemberId::of('bob'), $this->stage->clock->now());
    }

    public function testAShareOfAnUnknownLevelIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);

        $this->stage->sharing->unshare(new Page('1'), 'publish', MemberId::of('bob'));
    }

    public function testSharesWithAGroup(): void
    {
        $supporters = AccessGroup::of('supporters', [EntryPoint::Purchase]);
        $this->stage->sharing->share(new Page('1'), 'view', $supporters);
        $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Purchase);

        self::assertTrue($this->can('sam', 'view'));
        self::assertFalse($this->can('sid', 'view'));

        $this->stage->sharing->unshare(new Page('1'), 'view', $supporters);
        self::assertFalse($this->can('sam', 'view'));
    }

    public function testAShareWithAnUndeclaredGroupIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('The site declares no access group "patrons".');

        $this->stage->sharing->share(new Page('1'), 'view', AccessGroup::of('patrons', [EntryPoint::Bulk]));
    }

    public function testTheSharesOfAnItem(): void
    {
        $until = new DateTimeImmutable('2026-12-01 00:00:00 UTC');
        $this->stage->sharing->share(new Page('1'), 'view', MemberId::of('bob'), $until);
        $this->stage->sharing->share(new Page('1'), 'edit', AccessGroup::of('supporters', [EntryPoint::Purchase]));
        $this->stage->items->changeVisibility(new Page('1'), Visibility::Everyone);
        $this->stage->writer->grant(new Page('1')->accessReference(), 'view', Subject::fromString('group:newcomers#member'));

        $shares = $this->stage->sharing->shares(new Page('1'));

        self::assertEquals([
            new Share('edit', Share::GROUP, 'supporters', null),
            new Share('view', Share::GROUP, 'newcomers', null),
            new Share('view', Share::MEMBER, 'bob', $until),
        ], $shares);
        self::assertTrue($shares[0]->isGroup());
        self::assertFalse($shares[2]->isGroup());
    }

    public function testSharesLeaveOtherSubjectsOut(): void
    {
        $page = new Page('1')->accessReference();
        $this->stage->writer->grant($page, 'view', Subject::fromString('space:main#admin'));

        self::assertSame([], $this->stage->sharing->shares(new Page('1')));
    }

    public function testAMemberComesInThroughAnEntryPointAndTheMembershipEnds(): void
    {
        $until = $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Invitation);

        self::assertSame('2026-12-13 10:00:00', $until?->format('Y-m-d H:i:s'));
        self::assertSame(['supporters'], $this->stage->groups->groupsOf(MemberId::of('sam')));
        self::assertEquals([new Membership(MemberId::of('sam'), $until)], $this->stage->groups->members('supporters'));
        self::assertEquals([new MemberJoinedGroup('supporters', 'sam', 'invitation', $until)], $this->stage->events->events);

        $this->stage->clock->modify('+1 month');
        self::assertSame([], $this->stage->groups->groupsOf(MemberId::of('sam')));
    }

    public function testComingInAgainStartsTheDurationAgain(): void
    {
        $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Purchase);
        $this->stage->clock->modify('+20 days');
        $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Purchase);
        $this->stage->clock->modify('+20 days');

        self::assertSame(['supporters'], $this->stage->groups->groupsOf(MemberId::of('sam')));
    }

    public function testAnEntryPointTheGroupDoesNotAcceptIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('The access group "supporters" accepts no member through "bulk".');

        $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Bulk);
    }

    public function testAnUndeclaredGroupIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);

        $this->stage->groups->join('patrons', MemberId::of('sam'), EntryPoint::Bulk);
    }

    public function testLeaveAndDefaults(): void
    {
        self::assertSame(['newcomers'], $this->stage->groups->joinDefaults(MemberId::of('nia')));
        self::assertEquals([new Membership(MemberId::of('nia'), null)], $this->stage->groups->members('newcomers'));
        $this->stage->groups->leave('newcomers', MemberId::of('nia'));

        self::assertSame([], $this->stage->groups->members('newcomers'));
        self::assertEquals(new MemberLeftGroup('newcomers', 'nia'), $this->stage->events->events[1]);
        self::assertSame(['supporters', 'newcomers'], $this->stage->groups->names());
        self::assertSame('newcomers', $this->stage->groups->group('newcomers')->name);
    }

    public function testListsAreBounded(): void
    {
        foreach (['a', 'b', 'c'] as $member) {
            $this->stage->groups->join('newcomers', MemberId::of($member), EntryPoint::Default);
            $this->stage->groups->join('supporters', MemberId::of('a'), EntryPoint::Purchase);
        }

        self::assertCount(2, $this->stage->groups->members('newcomers', 2));
        self::assertSame(['newcomers'], $this->stage->groups->groupsOf(MemberId::of('a'), 1));
    }

    public function testWithoutEvents(): void
    {
        $groups = new AccessGroups($this->stage->writer, $this->stage->store, $this->stage->clock, [AccessGroup::of('g', [EntryPoint::Bulk])]);
        $groups->join('g', MemberId::of('a'), EntryPoint::Bulk);
        $groups->leave('g', MemberId::of('a'));

        self::assertSame([], $groups->members('g'));
        self::assertSame([], new AccessGroups($this->stage->writer, $this->stage->store, $this->stage->clock)->names());
    }

    private function can(string $member, string $level): bool
    {
        $this->stage->member->signIn($member);

        return $this->stage->access->can($level, new Page('1'));
    }
}
