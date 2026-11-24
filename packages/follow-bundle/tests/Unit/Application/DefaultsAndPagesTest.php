<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use Phil\FollowBundle\Application\AutoFollow;
use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Application\FollowerList;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\FollowState;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Application\RecipientResolver;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the application classes do when they are built with their defaults, where each page and each limit stops, and the
 * answers that depend on the order of two repositories' answers.
 */
#[CoversClass(AutoFollow::class)]
#[CoversClass(FollowRequests::class)]
#[CoversClass(Followers::class)]
#[CoversClass(FollowerList::class)]
#[CoversClass(Follows::class)]
#[CoversClass(FollowState::class)]
#[CoversClass(Mutuals::class)]
#[CoversClass(RecipientResolver::class)]
#[CoversClass(SubjectChain::class)]
#[CoversClass(Activity::class)]
final class DefaultsAndPagesTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testNothingIsFollowedAutomaticallyByDefault(): void
    {
        $auto = new AutoFollow($this->stage->follows, $this->stage->ports, $this->stage->ports, $this->stage->clock);
        $thread = Stage::subject('thread', '1');

        self::assertFalse($auto->created('a', $thread));
        self::assertFalse($auto->replied('a', $thread));
        self::assertNull($this->stage->follows->find('a', $thread));
    }

    public function testAnAcceptedMutualRelationshipMakesMembersFollowEachOtherByDefault(): void
    {
        $this->stage->requests->add($this->request('r1', 'a', Stage::subject('member', 'b'), 'b', RequestKind::Mutual));
        $requests = new FollowRequests($this->stage->requests, $this->stage->follows, $this->stage->mutuals, $this->stage->notifications, $this->stage->clock);

        $requests->accept('b', 'r1');

        self::assertInstanceOf(Follow::class, $this->stage->follows->find('a', Stage::subject('member', 'b')));
        self::assertInstanceOf(Follow::class, $this->stage->follows->find('b', Stage::subject('member', 'a')));
    }

    public function testThePagesOfRequestsHoldFiftyByDefaultAndAtLeastOne(): void
    {
        for ($i = 10; $i < 70; ++$i) {
            $this->stage->requests->add($this->request('p'.$i, 'r'.$i, Stage::subject('thread', 't'), 'b'));
            $this->stage->requests->add($this->request('s'.$i, 'a', Stage::subject('thread', 't'.$i), 'b'));
        }

        $requests = $this->stage->requests();

        self::assertCount(50, $requests->pendingFor('b'));
        self::assertCount(50, $requests->sentBy('a'));
        self::assertCount(51, $requests->pendingFor('b', null, 51));
        self::assertCount(51, $requests->sentBy('a', null, 51));
        self::assertCount(1, $requests->sentBy('a', null, 0));
        self::assertCount(1, $requests->pendingFor('b', null, 0));
    }

    public function testTheFollowingOfAMemberHoldsFiftyByDefault(): void
    {
        for ($i = 10; $i < 70; ++$i) {
            $this->stage->follows->save(new Follow('a', Stage::subject('thread', 't'.$i), FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now()));
        }

        $follows = $this->stage->follows();

        self::assertCount(50, $follows->following('a'));
        self::assertCount(51, $follows->following('a', null, 51));
        self::assertCount(1, $follows->following('a', null, 0));
    }

    public function testTheMutualsOfAMemberHoldFiftyByDefault(): void
    {
        for ($i = 10; $i < 70; ++$i) {
            $this->stage->mutuals->add(MemberPair::of('a', 'm'.$i), $this->stage->clock->now());
        }

        $mutuals = $this->stage->mutuals();

        self::assertCount(50, $mutuals->of('a'));
        self::assertCount(51, $mutuals->of('a', null, 51));
    }

    public function testMutualRelationshipsAreOffByDefault(): void
    {
        $mutuals = new Mutuals($this->stage->mutuals, $this->stage->requests, $this->stage->requests(), $this->stage->notifications, $this->stage->clock);

        self::assertFalse($mutuals->enabled());
        $this->expectException(FollowRefused::class);
        $mutuals->request('a', 'b');
    }

    public function testAPendingRequestIsNotReportedWhileRelationshipsAreOff(): void
    {
        $this->stage->requests->add($this->request('r1', 'a', Stage::subject('member', 'b'), 'b', RequestKind::Mutual));
        $off = new Mutuals($this->stage->mutuals, $this->stage->requests, $this->stage->requests(), $this->stage->notifications, $this->stage->clock, false);

        self::assertFalse($off->pending('a', 'b'));
        self::assertTrue($this->stage->mutuals()->pending('a', 'b'));
    }

    public function testTheRequestOfARelationshipCarriesItsIdentifierAndKind(): void
    {
        $this->stage->mutuals()->request('a', 'b');

        $sent = $this->stage->sent('follow.requested');
        self::assertCount(1, $sent);
        $request = $this->stage->requests->pendingFor('b', null, 10)[0];
        self::assertEquals(['request' => $request->id, 'kind' => 'mutual'], $sent[0]->data);
    }

    public function testEndingARelationshipWithdrawsTheRequestOfEitherMember(): void
    {
        $this->stage->requests->add($this->request('r1', 'b', Stage::subject('member', 'a'), 'a', RequestKind::Mutual));

        self::assertTrue($this->stage->mutuals()->end('a', 'b'));
        self::assertNull($this->stage->requests->find('r1'));
    }

    public function testEndingARelationshipThatHasARequestAlsoEndsBoth(): void
    {
        $this->stage->mutuals->add(MemberPair::of('a', 'b'), $this->stage->clock->now());
        $this->stage->requests->add($this->request('r1', 'a', Stage::subject('member', 'b'), 'b', RequestKind::Mutual));

        self::assertTrue($this->stage->mutuals()->end('a', 'b'));
        self::assertFalse($this->stage->mutuals->exists(MemberPair::of('a', 'b')));
        self::assertNull($this->stage->requests->find('r1'));
    }

    public function testAnEndedRelationshipStaysEndedWhenAnotherWorkerTookTheRequest(): void
    {
        $pending = $this->request('r1', 'a', Stage::subject('member', 'b'), 'b', RequestKind::Mutual);
        $mutuals = self::createStub(MutualRepository::class);
        $mutuals->method('remove')->willReturn(true);
        $requests = self::createStub(FollowRequestRepository::class);
        $requests->method('findPending')->willReturnOnConsecutiveCalls($pending, null);
        $requests->method('remove')->willReturn(false);

        $relationships = new Mutuals($mutuals, $requests, $this->stage->requests(), $this->stage->notifications, $this->stage->clock, true);

        self::assertTrue($relationships->end('a', 'b'));
    }

    public function testUnfollowingReportsTheFollowAndTheRequestBoth(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->stage->follows->save(new Follow('a', $thread, FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now()));
        $this->stage->requests->add($this->request('r1', 'a', $thread, 'o'));

        self::assertTrue($this->stage->follows()->unfollow('a', $thread));
        self::assertNull($this->stage->requests->find('r1'));
        self::assertNull($this->stage->follows->find('a', $thread));
    }

    public function testUnfollowingReportsTheFollowWhenAnotherWorkerTookTheRequest(): void
    {
        $thread = Stage::subject('thread', '1');
        $follows = self::createStub(FollowRepository::class);
        $follows->method('remove')->willReturn(true);
        $requests = self::createStub(FollowRequestRepository::class);
        $requests->method('findPending')->willReturn($this->request('r1', 'a', $thread, 'o'));
        $requests->method('remove')->willReturn(false);

        $service = new Follows($follows, $requests, $this->stage->deliveries, $this->stage->ports, $this->stage->ports, $this->stage->chain, $this->stage->notifications, $this->stage->clock);

        self::assertTrue($service->unfollow('a', $thread));
    }

    public function testAMemberIsCheckedBeforeTheyAreComparedWithTheSubject(): void
    {
        try {
            $this->stage->follows()->follow('not an identifier', Stage::subject('thread', '1'));
            self::fail('A member is an identifier.');
        } catch (InvalidFollow) {
            self::assertSame(0, $this->stage->ports->accessCalls, 'Nothing is asked of the application for a member that is none.');
        }
    }

    public function testTheStateOfAMemberReadsOnlyWhatItNeeds(): void
    {
        $this->stage->log->statements = 0;
        $state = $this->stage->follows()->state('a', Stage::subject('thread', '1'));

        self::assertNull($state->level());
        self::assertSame(2, $this->stage->log->statements, 'The follow of the subject, and the request that waits.');
    }

    public function testTheOwnLevelWinsOverTheInheritedOne(): void
    {
        $own = new Follow('a', Stage::subject('thread', '1'), FollowLevel::Mentions, new EventPreferences(), $this->stage->clock->now());
        $inherited = new Follow('a', Stage::subject('forum', '1'), FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now());

        self::assertSame(FollowLevel::Mentions, new FollowState($own, $inherited)->level());
        self::assertSame(FollowLevel::Watching, new FollowState(null, $inherited)->level());
        self::assertFalse(new FollowState()->pending);
    }

    public function testTheFollowersOfAMemberAreShownByDefault(): void
    {
        $followers = new Followers($this->stage->follows, $this->stage->visibility);

        self::assertFalse($followers->isHidden(Stage::subject('member', 'x'), 'y'));
        self::assertFalse($followers->followersHidden('x'));
        self::assertFalse(new FollowerList()->hidden);
    }

    public function testAPageOfFollowersHoldsFiftyByDefaultAndReadsOneMoreThanItShows(): void
    {
        $thread = Stage::subject('thread', '1');

        for ($i = 10; $i < 70; ++$i) {
            $this->stage->follows->save(new Follow('m'.$i, $thread, FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now()));
        }

        $page = $this->stage->followers()->followersOf($thread, null);
        self::assertCount(50, $page->members);
        self::assertSame('m59', $page->next);
        self::assertSame(60, $page->count);
        self::assertCount(51, $this->stage->followers()->followersOf($thread, null, null, 51)->members);

        $follows = $this->createMock(FollowRepository::class);
        $follows->expects(self::once())->method('followersOf')->with($thread, null, 11)->willReturn([]);
        $follows->method('countFollowers')->willReturn(0);
        new Followers($follows, $this->stage->visibility)->followersOf($thread, null, null, 10);
    }

    public function testTheChainOfASubjectGoesFiveParentsDeepByDefault(): void
    {
        $parents = [];

        for ($i = 1; $i <= 10; ++$i) {
            $parents[] = Stage::subject('forum', (string) $i);
        }

        $this->stage->ports->parents['thread:1'] = $parents;

        self::assertCount(6, new SubjectChain($this->stage->ports)->of(Stage::subject('thread', '1')));
    }

    public function testTheMentionsOfAnActivityAreAListWithoutDuplicates(): void
    {
        $activity = new Activity('a1', Stage::subject('thread', '1'), 'reply.created', null, ['x', 'x', 'y'], $this->stage->clock->now());

        self::assertSame(['x', 'y'], $activity->mentions);
    }

    public function testTheNearestSubjectOfTheChainDecidesEvenWhenItIsListedTwice(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '1');
        $activity = $this->activity($thread);

        $recipients = new RecipientResolver()->resolve($activity, [$thread, $forum, $thread], [
            $this->follow('m', $thread, FollowLevel::Ignoring),
            $this->follow('m', $forum, FollowLevel::Watching),
        ]);

        self::assertSame([], $recipients);
    }

    public function testTheFirstFollowOfASubjectIsKeptWhenAMemberHasTwoAtTheSameRank(): void
    {
        $thread = Stage::subject('thread', '1');

        $recipients = new RecipientResolver()->resolve($this->activity($thread), [$thread], [
            $this->follow('m', $thread, FollowLevel::Watching),
            $this->follow('m', $thread, FollowLevel::Ignoring),
        ]);

        self::assertSame(['m'], $recipients);
    }

    public function testAFollowOutsideTheChainDoesNotStopTheOthers(): void
    {
        $thread = Stage::subject('thread', '1');

        $recipients = new RecipientResolver()->resolve($this->activity($thread), [$thread], [
            $this->follow('x', Stage::subject('thread', '2'), FollowLevel::Watching),
            $this->follow('m', $thread, FollowLevel::Watching),
        ]);

        self::assertSame(['m'], $recipients);
    }

    private function activity(SubjectReference $subject): Activity
    {
        return new Activity('a1', $subject, 'reply.created', 'z', [], $this->stage->clock->now());
    }

    private function follow(string $member, SubjectReference $subject, FollowLevel $level): Follow
    {
        return new Follow($member, $subject, $level, new EventPreferences(), $this->stage->clock->now());
    }

    private function request(string $id, string $requester, SubjectReference $subject, string $approver, RequestKind $kind = RequestKind::Follow): FollowRequest
    {
        return new FollowRequest($id, $requester, $subject, $kind, $approver, FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now());
    }
}
