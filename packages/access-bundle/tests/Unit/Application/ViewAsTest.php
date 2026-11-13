<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Application;

use Override;
use Phil\AccessBundle\Application\Event\ViewAsStarted;
use Phil\AccessBundle\Application\Event\ViewAsStopped;
use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\ResourcePermissions\Budget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewAs::class)]
#[CoversClass(ViewAsRefused::class)]
#[CoversClass(ViewAsStarted::class)]
#[CoversClass(ViewAsStopped::class)]
final class ViewAsTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->spaces->addMember(MemberId::of('ann'));
        $this->stage->items->register(new Page('1'), MemberId::of('ann'));
        $this->stage->items->register(new Page('2'), MemberId::of('olga'), Visibility::Admins);
    }

    public function testAnAdminSeesWhatTheMemberSeesAndOnlyReads(): void
    {
        $this->stage->member->signIn('ada');
        self::assertTrue($this->stage->viewAs->isAllowed());
        self::assertFalse($this->stage->access->can('view', new Page('1')));
        self::assertTrue($this->stage->access->can('view', new Page('2')));

        $this->stage->viewAs->start(MemberId::of('ann'));

        self::assertSame('ann', $this->stage->viewAs->member()?->value);
        self::assertTrue($this->stage->subject->isViewingAs());
        self::assertSame('member:ann', $this->stage->subject->subject()->toString());
        self::assertTrue($this->stage->access->can('view', new Page('1')));
        self::assertFalse($this->stage->access->can('manage', new Page('1')));
        self::assertFalse($this->stage->access->can('view', new Page('2')));
        self::assertSame(['1'], $this->stage->access->visible('page')->ids());
        self::assertSame([], $this->stage->access->visible('page', 10, 'edit')->ids());
        self::assertTrue($this->stage->access->visible('page', 10, 'edit')->complete);
        self::assertEquals([new ViewAsStarted('ada', 'ann')], $this->stage->events->events);
        self::assertSame([['notice', 'ada started viewing the site as ann.']], $this->stage->logs);

        $this->stage->viewAs->stop();

        self::assertNull($this->stage->viewAs->member());
        self::assertNull($this->stage->viewAsStore->member);
        self::assertTrue($this->stage->access->can('view', new Page('2')));
        self::assertEquals(new ViewAsStopped('ada', 'ann'), $this->stage->events->events[1]);
        self::assertSame([['notice', 'ada started viewing the site as ann.'], ['notice', 'ada stopped viewing the site as ann.']], $this->stage->logs);
    }

    public function testStoppingWithoutAViewDoesNothing(): void
    {
        $this->stage->member->signIn('ada');
        $this->stage->viewAs->stop();

        self::assertSame([], $this->stage->events->events);
        self::assertSame([], $this->stage->logs);
    }

    public function testAViewEndsWhenTheAdminIsNoLongerOne(): void
    {
        $this->stage->member->signIn('ada');
        $this->stage->viewAs->start(MemberId::of('ann'));
        $this->stage->spaces->removeAdmin(MemberId::of('ada'));

        self::assertNull($this->stage->viewAs->member());
        self::assertFalse($this->stage->subject->isViewingAs());
        self::assertSame('member:ada', $this->stage->subject->subject()->toString());

        $this->stage->member->signIn(null);
        self::assertNull($this->stage->viewAs->member());
    }

    public function testAStoppedViewOfASignedOutAdminIsLogged(): void
    {
        $this->stage->member->signIn('ada');
        $this->stage->viewAs->start(MemberId::of('ann'));
        $this->stage->member->signIn(null);
        $this->stage->viewAs->stop();

        self::assertEquals(new ViewAsStopped('', 'ann'), $this->stage->events->events[1]);
    }

    public function testAVisitorIsRefused(): void
    {
        self::assertFalse($this->stage->viewAs->isAllowed());
        $this->expectException(ViewAsRefused::class);
        $this->expectExceptionMessageIs('Sign in to view the site as a member.');

        $this->stage->viewAs->start(MemberId::of('ann'));
    }

    public function testAMemberIsRefused(): void
    {
        $this->stage->member->signIn('ann');
        self::assertFalse($this->stage->viewAs->isAllowed());

        try {
            $this->stage->viewAs->start(MemberId::of('olga'));
            self::fail('The view should be refused.');
        } catch (ViewAsRefused $viewAsRefused) {
            self::assertSame('Only an admin of the space views the site as a member.', $viewAsRefused->getMessage());
        }

        self::assertNull($this->stage->viewAsStore->member);
        self::assertSame([], $this->stage->events->events);
    }

    public function testAnAdminDoesNotViewAsThemselves(): void
    {
        $this->stage->member->signIn('ada');

        $this->expectException(ViewAsRefused::class);
        $this->expectExceptionMessageIs('A member views the site as someone else, not as themselves.');

        $this->stage->viewAs->start(MemberId::of('ada'));
    }

    public function testAnAdminCheckPastItsBudgetRefuses(): void
    {
        $stage = new Stage(new Budget(maxReads: 1));
        $stage->spaces->addAdmin(AccessGroup::of('supporters', [EntryPoint::Purchase]));
        $stage->groups->join('supporters', MemberId::of('ada'), EntryPoint::Purchase);
        $stage->member->signIn('ada');

        self::assertFalse($stage->viewAs->isAllowed());
        self::assertSame([['warning', 'The admins of the space could not be read within the budget: The traversal read the store more than 1 times.']], $stage->logs);
    }

    public function testWithoutLoggerOrEvents(): void
    {
        $viewAs = new ViewAs($this->stage->viewAsStore, $this->stage->member, $this->stage->spaces);
        $this->stage->member->signIn('ada');
        $viewAs->start(MemberId::of('ann'));
        $viewAs->stop();

        self::assertNull($viewAs->member());
    }
}
