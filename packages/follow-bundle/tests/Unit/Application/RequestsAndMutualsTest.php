<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Application\Exception\UnknownFollowRequest;
use Phil\FollowBundle\Application\FollowOutcome;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FollowRequests::class)]
#[CoversClass(Mutuals::class)]
#[CoversClass(UnknownFollowRequest::class)]
final class RequestsAndMutualsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->ports->approvers['member:b'] = 'b';
    }

    public function testAcceptingARequestMakesTheFollowAtTheLevelAsked(): void
    {
        $profile = Stage::subject('member', 'b');
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);
        $this->stage->follows()->follow('a', $profile, FollowLevel::Custom, $events);
        $id = $this->pending('b')->id;

        $accepted = $this->stage->requests()->accept('b', $id);

        self::assertSame($id, $accepted->id);
        $follow = $this->stage->follows->find('a', $profile);
        self::assertInstanceOf(Follow::class, $follow);
        self::assertSame(FollowLevel::Custom, $follow->level());
        self::assertSame($events->levels, $follow->events()->levels);
        self::assertNull($this->stage->requests->find($id));
        $sent = $this->stage->sent(FollowTypes::ACCEPTED);
        self::assertSame(['a'], $sent[0]->recipients);
        self::assertSame(['member', 'b', 'b'], [$sent[0]->subjectType, $sent[0]->subjectId, $sent[0]->actor]);
        self::assertSame(['kind' => 'follow', 'request' => $id], $sent[0]->data);
    }

    public function testAcceptingTheRequestOfAMemberWhoIgnoredTheSubjectChangesTheirLevel(): void
    {
        $profile = Stage::subject('member', 'b');
        $follows = $this->stage->follows();
        $follows->follow('a', $profile, FollowLevel::Ignoring);
        $follows->follow('a', $profile, FollowLevel::Mentions);

        $this->stage->requests()->accept('b', $this->pending('b')->id);

        self::assertSame(FollowLevel::Mentions, $this->stage->follows->find('a', $profile)?->level());
    }

    public function testRejectingDeletesTheRequestWithoutAWord(): void
    {
        $this->stage->follows()->follow('a', Stage::subject('member', 'b'));
        $id = $this->pending('b')->id;
        $before = \count($this->stage->sent());

        $this->stage->requests()->reject('b', $id);

        self::assertNull($this->stage->requests->find($id));
        self::assertNull($this->stage->follows->find('a', Stage::subject('member', 'b')));
        self::assertCount($before, $this->stage->sent());
    }

    public function testOnlyTheApproverAnswersAndOnlyOnce(): void
    {
        $this->stage->follows()->follow('a', Stage::subject('member', 'b'));
        $id = $this->pending('b')->id;
        $requests = $this->stage->requests();

        foreach ([
            static fn (): FollowRequest => $requests->accept('c', $id),
            static fn () => $requests->reject('a', $id),
            static fn (): FollowRequest => $requests->accept('b', 'unknown'),
            static fn () => $requests->withdraw('b', $id),
        ] as $refused) {
            try {
                $refused();
                self::fail('Refused.');
            } catch (UnknownFollowRequest $unknownFollowRequest) {
                self::assertSame('No such request waits for this member.', $unknownFollowRequest->getMessage());
            }
        }

        $requests->accept('b', $id);
        $this->expectException(UnknownFollowRequest::class);
        $requests->reject('b', $id);
    }

    public function testTheRequesterWithdrawsTheirRequest(): void
    {
        $this->stage->follows()->follow('a', Stage::subject('member', 'b'));
        $id = $this->pending('b')->id;

        $this->stage->requests()->withdraw('a', $id);

        self::assertSame([], $this->stage->requests()->pendingFor('b'));
        $this->expectException(UnknownFollowRequest::class);
        $this->stage->requests()->withdraw('a', $id);
    }

    public function testTheRequestsArePagedOldestFirst(): void
    {
        $follows = $this->stage->follows();

        foreach (['c', 'a', 'd'] as $member) {
            $follows->follow($member, Stage::subject('member', 'b'));
            $this->stage->clock->modify('+1 second');
        }

        $requesters = static fn (array $requests): array => array_map(static fn (FollowRequest $request): string => $request->requester, $requests);
        $requests = $this->stage->requests();
        self::assertSame(['c', 'a', 'd'], $requesters($requests->pendingFor('b')));
        $first = $requests->pendingFor('b', null, 1);
        self::assertSame(['c'], $requesters($first));
        self::assertSame(['a', 'd'], $requesters($requests->pendingFor('b', $first[0]->id, 5)));
        self::assertSame(['c'], $requesters($requests->pendingFor('b', null, 0)));
        self::assertSame(['a'], $requesters($requests->sentBy('a')));
        self::assertSame([], $requests->sentBy('a', $requests->sentBy('a')[0]->id));
        self::assertCount(1, $requests->sentBy('a', null, 0));
    }

    public function testAMutualRelationshipIsAskedThenAccepted(): void
    {
        $mutuals = $this->stage->mutuals();

        self::assertSame(FollowOutcome::Requested, $mutuals->request('a', 'c'));
        self::assertTrue($mutuals->pending('a', 'c'));
        self::assertFalse($mutuals->pending('c', 'a'));
        self::assertFalse($mutuals->areMutual('a', 'c'));
        $sent = $this->stage->sent(FollowTypes::REQUESTED);
        self::assertSame(['c'], $sent[0]->recipients);
        self::assertSame('mutual', $sent[0]->data['kind']);
        self::assertSame(FollowOutcome::Requested, $mutuals->request('a', 'c'));
        self::assertCount(1, $this->stage->sent(FollowTypes::REQUESTED));

        $this->stage->requests()->accept('c', $this->pending('c')->id);

        self::assertTrue($mutuals->areMutual('a', 'c'));
        self::assertTrue($mutuals->areMutual('c', 'a'));
        self::assertFalse($mutuals->pending('a', 'c'));
        self::assertSame(FollowLevel::Watching, $this->stage->follows->find('a', Stage::subject('member', 'c'))?->level());
        self::assertSame(FollowLevel::Watching, $this->stage->follows->find('c', Stage::subject('member', 'a'))?->level());
        self::assertSame(['c'], $mutuals->of('a'));
        self::assertSame(['a'], $mutuals->of('c'));
        self::assertSame(FollowOutcome::Done, $mutuals->request('c', 'a'));
    }

    public function testAcceptingARelationshipKeepsTheLevelsTheMembersChose(): void
    {
        $this->stage->follows()->follow('c', Stage::subject('member', 'a'), FollowLevel::Ignoring);
        $mutuals = $this->stage->mutuals();
        $mutuals->request('a', 'c');

        $this->stage->requests()->accept('c', $this->pending('c')->id);

        self::assertSame(FollowLevel::Ignoring, $this->stage->follows->find('c', Stage::subject('member', 'a'))?->level());
        self::assertSame(FollowLevel::Watching, $this->stage->follows->find('a', Stage::subject('member', 'c'))?->level());
    }

    public function testARelationshipNeedNotMakeTheMembersFollowEachOther(): void
    {
        $mutuals = $this->stage->mutuals(followEachOther: false);
        $mutuals->request('a', 'c');

        $this->stage->requests(false)->accept('c', $this->pending('c')->id);

        self::assertTrue($mutuals->areMutual('a', 'c'));
        self::assertNull($this->stage->follows->find('a', Stage::subject('member', 'c')));
    }

    public function testTwoMembersWhoAskEachOtherAreJoinedAtOnce(): void
    {
        $mutuals = $this->stage->mutuals();
        $mutuals->request('a', 'c');

        self::assertSame(FollowOutcome::Done, $mutuals->request('c', 'a'));
        self::assertTrue($mutuals->areMutual('a', 'c'));
        self::assertSame([], $this->stage->requests->pendingFor('c', null, 5));
        self::assertSame(['a'], $this->stage->sent(FollowTypes::ACCEPTED)[0]->recipients);
    }

    public function testEndingARelationshipKeepsTheFollowsAndWithdrawsTheRequests(): void
    {
        $mutuals = $this->stage->mutuals();
        self::assertFalse($mutuals->end('a', 'c'));
        $mutuals->request('a', 'c');
        self::assertTrue($mutuals->end('c', 'a'), 'Either member withdraws a waiting request.');
        self::assertFalse($mutuals->pending('a', 'c'));

        $mutuals->request('a', 'c');
        $mutuals->request('c', 'a');
        self::assertTrue($mutuals->end('a', 'c'));
        self::assertFalse($mutuals->areMutual('a', 'c'));
        self::assertNotNull($this->stage->follows->find('a', Stage::subject('member', 'c')));
    }

    public function testRelationshipsAreTurnedOffByDefault(): void
    {
        $mutuals = $this->stage->mutuals(false);
        $this->stage->mutuals->add(MemberPair::of('a', 'c'), $this->stage->clock->now());

        self::assertFalse($mutuals->enabled());
        self::assertFalse($mutuals->areMutual('a', 'c'));
        self::assertFalse($mutuals->pending('a', 'c'));
        self::assertSame([], $mutuals->of('a'));

        foreach ([static fn (): FollowOutcome => $mutuals->request('a', 'c'), static fn (): bool => $mutuals->end('a', 'c')] as $refused) {
            try {
                $refused();
                self::fail('Refused.');
            } catch (FollowRefused $followRefused) {
                self::assertSame('Mutual relationships are turned off: set "phil_follow.mutual.enabled" to true.', $followRefused->getMessage());
            }
        }
    }

    public function testAMemberIsNotTheirOwnRelationship(): void
    {
        $mutuals = $this->stage->mutuals();
        self::assertTrue($mutuals->enabled());
        self::assertFalse($mutuals->areMutual('a', 'a'));
        self::assertFalse($mutuals->pending('a', 'a'));
        $this->expectExceptionObject(FollowRefused::self());
        $mutuals->request('a', 'a');
    }

    public function testTheRelationshipsArePaged(): void
    {
        $mutuals = $this->stage->mutuals();

        foreach (['d', 'b', 'f', 'e'] as $other) {
            $this->stage->mutuals->add(MemberPair::of('c', $other), $this->stage->clock->now());
        }

        self::assertSame(['b', 'd', 'e', 'f'], $mutuals->of('c'));
        self::assertSame(['b', 'd'], $mutuals->of('c', null, 2));
        self::assertSame(['e', 'f'], $mutuals->of('c', 'd', 2));
        self::assertSame(['b'], $mutuals->of('c', null, 0));
    }

    private function pending(string $approver): FollowRequest
    {
        $pending = $this->stage->requests->pendingFor($approver, null, 1);
        self::assertCount(1, $pending);

        return $pending[0];
    }
}
