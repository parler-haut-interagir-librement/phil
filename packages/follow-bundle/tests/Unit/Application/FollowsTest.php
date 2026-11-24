<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Application\FollowOutcome;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\FollowState;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Follows::class)]
#[CoversClass(FollowState::class)]
#[CoversClass(FollowRefused::class)]
final class FollowsTest extends TestCase
{
    private Stage $stage;

    private Follows $follows;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->follows = $this->stage->follows();
    }

    public function testAMemberFollowsAnOpenSubjectAtOnce(): void
    {
        $thread = Stage::subject('thread', '1');

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', $thread));

        $follow = $this->stage->follows->find('a', $thread);
        self::assertInstanceOf(Follow::class, $follow);
        self::assertSame(FollowLevel::Watching, $follow->level());
        self::assertEquals($this->stage->clock->now(), $follow->followedAt);
        self::assertSame([], $this->stage->sent(), 'Following a thread notifies no one.');
    }

    public function testAMemberChangesTheirLevelFreely(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->follows->follow('a', $thread);
        $this->stage->ports->approvers['thread:1'] = 'owner';
        $this->stage->ports->refused = ['a'];
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', $thread, FollowLevel::Custom, $events));
        self::assertSame($events->levels, $this->stage->follows->find('a', $thread)?->events()->levels);
        $this->stage->log->statements = 0;
        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', $thread, FollowLevel::Custom, $events));
        self::assertSame(1, $this->stage->log->statements, 'An unchanged level is not written.');
    }

    public function testFollowingAMemberTellsThem(): void
    {
        $this->follows->follow('a', Stage::subject('member', 'b'));

        $sent = $this->stage->sent(FollowTypes::FOLLOWED);
        self::assertCount(1, $sent);
        self::assertSame(['b'], $sent[0]->recipients);
        self::assertSame(['member', 'a'], [$sent[0]->subjectType, $sent[0]->subjectId]);
        self::assertSame('a', $sent[0]->actor);
    }

    public function testIgnoringAMemberTellsThemNothing(): void
    {
        $this->follows->follow('a', Stage::subject('member', 'b'), FollowLevel::Ignoring);

        self::assertSame([], $this->stage->sent());
    }

    public function testAMemberDoesNotFollowThemselves(): void
    {
        $this->expectExceptionObject(FollowRefused::self());
        $this->follows->follow('a', Stage::subject('member', 'a'));
    }

    public function testTheMemberIsAnIdentifier(): void
    {
        $this->expectException(InvalidFollow::class);
        $this->follows->follow('a@b', Stage::subject('thread', '1'));
    }

    public function testAMemberWithoutAccessDoesNotFollow(): void
    {
        $this->stage->ports->refused = ['a'];

        try {
            $this->follows->follow('a', Stage::subject('thread', '1'));
            self::fail('Refused.');
        } catch (FollowRefused $followRefused) {
            self::assertSame('The member may not follow this subject.', $followRefused->getMessage());
        }

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', Stage::subject('thread', '1'), FollowLevel::Ignoring), 'Ignoring needs no access.');
    }

    public function testALockedProfileTurnsTheFollowIntoARequest(): void
    {
        $profile = Stage::subject('member', 'b');
        $this->stage->ports->approvers['member:b'] = 'b';

        self::assertSame(FollowOutcome::Requested, $this->follows->follow('a', $profile, FollowLevel::Mentions));
        self::assertNull($this->stage->follows->find('a', $profile));
        $request = $this->stage->requests->findPending('a', $profile, RequestKind::Follow);
        self::assertSame(['b', FollowLevel::Mentions], [$request?->approver, $request?->level]);
        $sent = $this->stage->sent(FollowTypes::REQUESTED);
        self::assertSame(['b'], $sent[0]->recipients);
        self::assertSame(['member', 'a', 'a'], [$sent[0]->subjectType, $sent[0]->subjectId, $sent[0]->actor]);
        self::assertSame(['kind' => 'follow', 'request' => $request?->id], $sent[0]->data);
        self::assertSame([], $this->stage->sent(FollowTypes::FOLLOWED));

        self::assertSame(FollowOutcome::Requested, $this->follows->follow('a', $profile));
        self::assertCount(1, $this->stage->sent(FollowTypes::REQUESTED), 'A second ask waits on the same request, without a word.');
        self::assertTrue($this->follows->state('a', $profile)->pending);
    }

    public function testARequestKeepsTheEventsOfTheCustomLevelOnly(): void
    {
        $this->stage->ports->approvers['group:1'] = 'owner';
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);

        $this->follows->follow('a', Stage::subject('group', '1'), FollowLevel::Watching, $events);
        $this->follows->follow('c', Stage::subject('group', '1'), FollowLevel::Custom, $events);

        self::assertSame([], $this->stage->requests->sentBy('a', null, 10)[0]->events->levels);
        self::assertSame($events->levels, $this->stage->requests->sentBy('c', null, 10)[0]->events->levels);
    }

    public function testTheApproverOfTheirOwnSubjectFollowsAtOnce(): void
    {
        $this->stage->ports->approvers['group:1'] = 'a';

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', Stage::subject('group', '1')));
    }

    public function testIgnoringALockedProfileNeedsNoApprovalButFollowingItThenDoes(): void
    {
        $profile = Stage::subject('member', 'b');
        $this->stage->ports->approvers['member:b'] = 'b';

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', $profile, FollowLevel::Ignoring));
        self::assertSame(FollowOutcome::Requested, $this->follows->follow('a', $profile));
        self::assertSame(FollowLevel::Ignoring, $this->stage->follows->find('a', $profile)?->level(), 'The member still ignores it while the request waits.');
    }

    public function testFollowingAnIgnoredOpenSubjectChangesTheLevel(): void
    {
        $profile = Stage::subject('member', 'b');
        $this->follows->follow('a', $profile, FollowLevel::Ignoring);
        $followedAt = $this->stage->follows->find('a', $profile)?->followedAt;
        $this->stage->clock->modify('+1 day');

        self::assertSame(FollowOutcome::Done, $this->follows->follow('a', $profile));

        $follow = $this->stage->follows->find('a', $profile);
        self::assertInstanceOf(Follow::class, $follow);
        self::assertSame(FollowLevel::Watching, $follow->level());
        self::assertEquals($followedAt, $follow->followedAt);
        self::assertCount(1, $this->stage->sent(FollowTypes::FOLLOWED));
    }

    public function testUnfollowingRemovesTheFollowOrWithdrawsTheRequest(): void
    {
        $thread = Stage::subject('thread', '1');
        self::assertFalse($this->follows->unfollow('a', $thread));

        $this->follows->follow('a', $thread);
        self::assertTrue($this->follows->unfollow('a', $thread));
        self::assertNull($this->stage->follows->find('a', $thread));

        $this->stage->ports->approvers['thread:1'] = 'owner';
        $this->follows->follow('a', $thread);
        self::assertTrue($this->follows->unfollow('a', $thread));
        self::assertFalse($this->follows->state('a', $thread)->pending);
    }

    public function testUnfollowingAMemberKeepsTheMutualRelationship(): void
    {
        $mutuals = $this->stage->mutuals();
        $mutuals->request('a', 'b');
        $this->stage->requests()->accept('b', $this->stage->requests->pendingFor('b', null, 1)[0]->id);

        self::assertTrue($this->follows->unfollow('a', Stage::subject('member', 'b')));
        self::assertTrue($this->follows->unfollow('b', Stage::subject('member', 'a')));
        self::assertTrue($mutuals->areMutual('a', 'b'));
        self::assertTrue($mutuals->areMutual('b', 'a'));
    }

    public function testTheStateReadsTheMembersOwnFollowElseTheNearestParents(): void
    {
        $reply = Stage::subject('reply', '1');
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '1');
        $this->stage->ports->parents['reply:1'] = [$thread, $forum];
        self::assertEquals(new FollowState(), $this->follows->state('a', $reply));
        self::assertNull($this->follows->state('a', $reply)->level());
        self::assertFalse($this->follows->state('a', $reply)->follows());

        $this->follows->follow('a', $forum, FollowLevel::Mentions);
        self::assertSame(FollowLevel::Mentions, $this->follows->state('a', $reply)->level());
        $this->follows->follow('a', $thread, FollowLevel::Ignoring);
        $state = $this->follows->state('a', $reply);
        self::assertSame(FollowLevel::Ignoring, $state->level());
        self::assertSame($thread->key(), $state->inherited?->subject->key());
        self::assertFalse($state->follows());

        $this->follows->follow('a', $reply);
        $state = $this->follows->state('a', $reply);
        self::assertSame(FollowLevel::Watching, $state->level());
        self::assertNull($state->inherited);
        self::assertTrue($state->follows());
    }

    public function testTheMembersFollowsArePagedBySubject(): void
    {
        foreach (['thread:2', 'forum:1', 'thread:1', 'thread:10'] as $key) {
            [$type, $id] = explode(':', $key);
            $this->follows->follow('a', Stage::subject($type, $id));
        }

        $this->follows->follow('b', Stage::subject('thread', '3'));

        $keys = static fn (array $follows): array => array_map(static fn (Follow $follow): string => $follow->subject->key(), $follows);
        self::assertSame(['forum:1', 'thread:1', 'thread:10', 'thread:2'], $keys($this->follows->following('a')));
        self::assertSame(['forum:1', 'thread:1'], $keys($this->follows->following('a', null, 2)));
        self::assertSame(['thread:10', 'thread:2'], $keys($this->follows->following('a', 'thread:1', 2)));
        self::assertSame(['forum:1'], $keys($this->follows->following('a', null, 0)), 'A page holds one follow at least.');
        self::assertCount(4, $this->follows->following('a', null, 1000));
    }

    public function testSeeingASubjectClearsItsMark(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->stage->deliveries->markNotified($thread, ['a', 'b'], $this->stage->clock->now());

        $this->follows->seen('a', $thread);

        self::assertSame(['b'], $this->stage->deliveries->unseen($thread, ['a', 'b'], $this->stage->clock->now()));
    }
}
