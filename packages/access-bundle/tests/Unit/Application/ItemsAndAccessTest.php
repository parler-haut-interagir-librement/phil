<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Application;

use Override;
use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Items::class)]
#[CoversClass(ItemAccess::class)]
#[CoversClass(AccessSubject::class)]
#[CoversClass(Spaces::class)]
final class ItemsAndAccessTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->spaces->addMember(MemberId::of('ann'));
    }

    public function testAnItemIsPrivateToItsOwnerByDefault(): void
    {
        $this->stage->items->register(new Page('1'), MemberId::of('olga'));

        self::assertSame(Visibility::Owner, $this->stage->items->visibility(new Page('1')));
        self::assertSame('olga', $this->stage->items->owner(new Page('1'))?->value);
        self::assertTrue($this->can('olga', 'manage', '1'));
        self::assertTrue($this->can('olga', 'view', '1'));
        self::assertFalse($this->can('ada', 'view', '1'));
        self::assertFalse($this->can('ann', 'view', '1'));
        self::assertFalse($this->can(null, 'view', '1'));
    }

    public function testEachVisibility(): void
    {
        $page = ResourceReference::of('page', '1');
        $this->stage->items->register($page, MemberId::of('olga'), Visibility::Everyone);

        self::assertTrue($this->can(null, 'view', '1'));
        self::assertTrue($this->can('zed', 'view', '1'));
        self::assertFalse($this->can('zed', 'comment', '1'));

        $this->stage->items->changeVisibility($page, Visibility::Members);
        self::assertSame(Visibility::Members, $this->stage->items->visibility($page));
        self::assertFalse($this->can(null, 'view', '1'));
        self::assertFalse($this->can('zed', 'view', '1'));
        self::assertTrue($this->can('ann', 'view', '1'));
        self::assertTrue($this->can('ada', 'view', '1'));

        $this->stage->items->changeVisibility($page, Visibility::Admins);
        self::assertFalse($this->can('ann', 'view', '1'));
        self::assertTrue($this->can('ada', 'view', '1'));
        self::assertTrue($this->can('olga', 'view', '1'));

        $this->stage->items->changeVisibility($page, Visibility::Owner);
        self::assertFalse($this->can('ada', 'view', '1'));
        self::assertTrue($this->can('olga', 'view', '1'));
        self::assertCount(1, $this->stage->store->subjects($page, 'visibility', 10));
    }

    public function testRegisteringAgainReplacesOwnerSpaceAndVisibility(): void
    {
        $page = new Page('1');
        $this->stage->items->register($page, MemberId::of('olga'), Visibility::Members);
        $this->stage->items->register($page, MemberId::of('otto'), Visibility::Members, 'other');

        self::assertSame('otto', $this->stage->items->owner($page)?->value);
        self::assertFalse($this->can('olga', 'manage', '1'));
        self::assertFalse($this->can('ann', 'view', '1'));
        self::assertCount(1, $this->stage->store->subjects($page->accessReference(), 'space', 10));
        self::assertCount(1, $this->stage->store->subjects($page->accessReference(), 'view', 10));

        $this->stage->spaces->addMember(MemberId::of('ann'), 'other');
        self::assertTrue($this->can('ann', 'view', '1'));
    }

    public function testAnotherDefaultVisibility(): void
    {
        $items = new Items($this->stage->writer, $this->stage->store, $this->stage->levels, $this->stage->spaces, Visibility::Members);
        $items->register(new Page('1'), MemberId::of('olga'));

        self::assertSame(Visibility::Members, $items->visibility(new Page('1')));
    }

    public function testAnUnregisteredItem(): void
    {
        self::assertNull($this->stage->items->visibility(new Page('9')));
        self::assertNull($this->stage->items->owner(new Page('9')));

        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('page:9 has no space: register it first.');

        $this->stage->items->changeVisibility(new Page('9'), Visibility::Everyone);
    }

    public function testAVisibilityMarkerOfAnotherValueReadsAsNone(): void
    {
        $this->stage->writer->grant(ResourceReference::of('page', '1'), 'visibility', ResourceReference::of('visibility', 'secret'));

        self::assertNull($this->stage->items->visibility(new Page('1')));
    }

    public function testTransferAndForget(): void
    {
        $page = new Page('1');
        $this->stage->items->register($page, MemberId::of('olga'));
        $this->stage->items->transfer($page, MemberId::of('otto'));

        self::assertSame('otto', $this->stage->items->owner($page)?->value);
        self::assertFalse($this->can('olga', 'view', '1'));
        self::assertSame(3, $this->stage->items->forget($page));
        self::assertNull($this->stage->items->owner($page));
    }

    public function testGroupsOfASpace(): void
    {
        $this->stage->groups->join('supporters', MemberId::of('sam'), EntryPoint::Purchase);
        $this->stage->spaces->addAdmin(AccessGroup::of('supporters', [EntryPoint::Bulk]));
        $this->stage->spaces->addMember(AccessGroup::of('newcomers', [EntryPoint::Default]));

        self::assertTrue($this->stage->spaces->isAdmin(MemberId::of('sam')));
        self::assertTrue($this->stage->spaces->isMember(MemberId::of('sam')));
        self::assertTrue($this->stage->spaces->isMember(MemberId::of('ada')));
        self::assertFalse($this->stage->spaces->isAdmin(MemberId::of('ann')));

        $this->stage->spaces->removeAdmin(AccessGroup::of('supporters', [EntryPoint::Bulk]));
        $this->stage->spaces->removeAdmin(MemberId::of('ada'));
        $this->stage->spaces->removeMember(MemberId::of('ann'));
        $this->stage->spaces->removeMember(AccessGroup::of('newcomers', [EntryPoint::Default]));
        self::assertFalse($this->stage->spaces->isAdmin(MemberId::of('sam')));
        self::assertFalse($this->stage->spaces->isMember(MemberId::of('ada')));
        self::assertFalse($this->stage->spaces->isMember(MemberId::of('ann')));
        self::assertSame('space:other', $this->stage->spaces->space('other')->toString());
        self::assertSame('space:main', $this->stage->spaces->space()->toString());
    }

    public function testASpaceNameFollowsTheRules(): void
    {
        foreach (['', 'a#b', '*'] as $space) {
            try {
                Spaces::reference($space);
                self::fail(\sprintf('"%s" should be refused.', $space));
            } catch (InvalidAccess $invalid) {
                self::assertStringStartsWith(\sprintf('"%s" cannot name a space: ', $space), $invalid->getMessage());
            }
        }

        $this->expectException(InvalidAccess::class);

        new Spaces($this->stage->writer, $this->stage->checker, 'a#b');
    }

    public function testAllowsAndExplain(): void
    {
        $this->stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Members);

        self::assertTrue($this->stage->access->allows(Subject::of(MemberId::of('ann')->reference()), 'view', new Page('1')));
        self::assertFalse($this->stage->access->allows(Subject::of(MemberId::of('zed')->reference()), 'view', new Page('1')));

        $this->stage->member->signIn('ann');
        self::assertSame(['page:1#view@space:main#member', 'space:main#member@member:ann'], $this->stage->access->explain('view', new Page('1'))?->lines());
        self::assertNull($this->stage->access->explain('edit', new Page('1')));
    }

    public function testAnUnknownLevelIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);

        $this->stage->access->allows(Subject::fromString('member:a'), 'publish', new Page('1'));
    }

    public function testAnUnknownLevelIsRefusedByCan(): void
    {
        $this->expectException(InvalidAccess::class);

        $this->stage->access->can('publish', new Page('1'));
    }

    public function testVisible(): void
    {
        $this->stage->items->register(new Page('2'), MemberId::of('olga'), Visibility::Members);
        $this->stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Everyone);
        $this->stage->items->register(new Page('3'), MemberId::of('olga'));
        $this->stage->member->signIn('ann');

        self::assertSame(['1', '2'], $this->stage->access->visible('page')->ids());
        self::assertSame(['1'], $this->stage->access->visible('page', 1)->ids());
        self::assertSame([], $this->stage->access->visible('page', 10, 'edit')->ids());

        $this->stage->member->signIn('olga');
        self::assertSame(['1', '2', '3'], $this->stage->access->visible('page', 10, 'manage')->ids());
    }

    public function testAVisitor(): void
    {
        self::assertSame('visitor:anonymous', $this->stage->subject->subject()->toString());
        self::assertFalse($this->stage->subject->isViewingAs());
        self::assertSame('visitor:anonymous', new AccessSubject($this->stage->member)->subject()->toString());
    }

    public function testACheckPastItsBudgetIsRefusedAndLogged(): void
    {
        $stage = new Stage(new Budget(maxReads: 1));
        $stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Members);
        $stage->member->signIn('ann');

        self::assertFalse($stage->access->can('view', new Page('1')));
        self::assertNull($stage->access->explain('view', new Page('1')));
        $list = $stage->access->visible('page');
        self::assertSame([], $list->ids());
        self::assertFalse($list->complete);
        self::assertSame([
            ['warning', 'Access to page:1 refused: The traversal read the store more than 1 times.'],
            ['warning', 'The page items could not be listed: The traversal read the store more than 1 times.'],
        ], $stage->logs);
    }

    private function can(?string $member, string $level, string $page): bool
    {
        $this->stage->member->signIn($member);

        return $this->stage->access->can($level, ResourceReference::of('page', $page));
    }
}
