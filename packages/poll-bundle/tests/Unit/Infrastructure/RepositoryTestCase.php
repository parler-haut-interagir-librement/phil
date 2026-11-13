<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Poll\Ballot;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;

/**
 * What every store of polls and ballots does, whatever keeps them.
 */
abstract class RepositoryTestCase extends TestCase
{
    /**
     * @return array{PollRepository, VoteRepository}
     */
    abstract protected function stores(): array;

    protected function reread(PollRepository $polls, string $id): Poll
    {
        $poll = $polls->get($id);
        self::assertInstanceOf(Poll::class, $poll);

        return $poll;
    }

    protected function rereadVote(VoteRepository $votes, string $poll, ResourceReference $voter): Vote
    {
        $vote = $votes->ofVoter($poll, $voter);
        self::assertInstanceOf(Vote::class, $vote);

        return $vote;
    }

    public function testPollsAreKept(): void
    {
        [$polls] = $this->stores();
        $first = $this->poll('p1', '2026-11-08 10:00:00', ResourceReference::of('idea', '1'));
        $second = $this->poll('p0', '2026-11-08 10:00:00', ResourceReference::of('idea', '1'));
        $third = $this->poll('p2', '2026-11-08 09:00:00', ResourceReference::of('idea', '1'));
        $elsewhere = $this->poll('p3', '2026-11-08 08:00:00', ResourceReference::of('idea', '2'));
        $nowhere = $this->poll('p4', '2026-11-08 07:00:00', null);

        foreach ([$first, $second, $third, $elsewhere, $nowhere] as $poll) {
            $polls->save($poll);
        }

        $reread = $this->reread($polls, 'p1');
        self::assertSame(['Which?', 'idea:1', 'schulze', true, ResultsVisibility::AfterVoting], [$reread->question(), $reread->subject()?->toString(), $reread->counting(), $reread->isAnonymous(), $reread->resultsVisibility()]);
        self::assertEquals(PollRules::ranked(['a', 'b', 'c'], 1, 2, true), $reread->rules());
        self::assertSame('B', $reread->label('b'));
        self::assertSame('2026-11-08 10:00:00', $reread->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame(['p2', 'p0', 'p1'], array_map(static fn (Poll $poll): string => $poll->id, $polls->ofSubject(ResourceReference::of('idea', '1'))));
        self::assertNull($polls->get('nope'));

        $reread->close(new DateTimeImmutable('2026-11-08 12:00:00 UTC'));
        $reread->reschedule(new DateTimeImmutable('2026-11-08 09:00:00 UTC'), null);

        $polls->save($reread);
        $closed = $this->reread($polls, 'p1');
        self::assertSame(['12:00', '09:00', null], [$closed->closedAt()?->format('H:i'), $closed->opensAt()?->format('H:i'), $closed->closesAt()]);

        $polls->remove($closed);
        self::assertNull($polls->get('p1'));
        self::assertCount(2, $polls->ofSubject(ResourceReference::of('idea', '1')));
    }

    public function testTheRevisionOfTheBallotsIsRaisedInTheStore(): void
    {
        [$polls] = $this->stores();
        $polls->save($this->poll('p1', '2026-11-08 10:00:00', null));
        $polls->save($this->poll('p2', '2026-11-08 10:00:00', null));

        self::assertSame([0, 0, 0], [$polls->revision('p1'), $polls->revision('p2'), $polls->revision('nope')]);

        $polls->raiseRevision('p1');
        $polls->raiseRevision('p1');
        $polls->raiseRevision('nope');

        self::assertSame([2, 0, 0], [$polls->revision('p1'), $polls->revision('p2'), $polls->revision('nope')]);
        self::assertSame(1, $this->reread($polls, 'p1')->seats());

        $polls->remove($this->reread($polls, 'p1'));
        self::assertSame(0, $polls->revision('p1'));
    }

    public function testBallotsAreKept(): void
    {
        [, $votes] = $this->stores();
        $ada = ResourceReference::of('user', 'ada');
        $bob = ResourceReference::of('user', 'bob');

        $votes->add(Vote::named('v1', 'p1', $ada, Ballot::of(['b', 'a']), new DateTimeImmutable('2026-11-08 10:00:00 UTC')));
        $votes->add(Vote::named('v2', 'p1', $bob, Ballot::of(['a']), new DateTimeImmutable('2026-11-08 10:00:00 UTC')));
        $votes->add(Vote::named('v3', 'p2', $ada, Ballot::of(['c']), new DateTimeImmutable('2026-11-08 10:00:00 UTC')));

        $vote = $this->rereadVote($votes, 'p1', $ada);
        self::assertSame(['v1', ['b', 'a'], '10:00'], [$vote->id, $vote->ballot()->choices, $vote->castAt()?->format('H:i')]);
        self::assertNull($votes->ofVoter('p1', ResourceReference::of('user', 'cid')));
        self::assertSame('v3', $votes->ofVoter('p2', $ada)?->id);
        self::assertNull($votes->ofVoter('p2', $bob));
        self::assertSame(2, $votes->count('p1'));
        self::assertSame(0, $votes->count('p9'));
        self::assertEqualsCanonicalizing([['b', 'a'], ['a']], array_map(static fn (Ballot $ballot): array => $ballot->choices, iterator_to_array($votes->ballots('p1'), false)));

        $vote->change(Ballot::of([]), new DateTimeImmutable('2026-11-08 11:00:00 UTC'));
        $votes->save($vote);
        self::assertSame([[], '11:00'], [$this->rereadVote($votes, 'p1', $ada)->ballot()->choices, $this->rereadVote($votes, 'p1', $ada)->castAt()?->format('H:i')]);

        $votes->remove($this->rereadVote($votes, 'p1', $bob));
        self::assertNull($votes->ofVoter('p1', $bob));
        self::assertSame(1, $votes->count('p1'));
    }

    public function testAVoterHasOneBallotPerPoll(): void
    {
        [, $votes] = $this->stores();
        $ada = ResourceReference::of('user', 'ada');
        $votes->add(Vote::named('v1', 'p1', $ada, Ballot::of(['a']), new DateTimeImmutable()));

        $this->expectExceptionObject(AlreadyVoted::in('p1'));

        $votes->add(Vote::named('v2', 'p1', $ada, Ballot::of(['b']), new DateTimeImmutable()));
    }

    public function testAnonymousBallotsAndParticipations(): void
    {
        [, $votes] = $this->stores();
        $ada = ResourceReference::of('user', 'ada');

        $votes->add(Vote::anonymous('v1', 'p1', str_repeat('a', 64), Ballot::of(['b'])), Participation::of('p1', $ada));
        $votes->add(Vote::anonymous('v2', 'p1', str_repeat('b', 64), Ballot::of(['a'])), Participation::of('p1', ResourceReference::of('user', 'bob')));
        $votes->add(Vote::anonymous('v3', 'p2', str_repeat('c', 64), Ballot::of(['a'])), Participation::of('p2', $ada));

        self::assertTrue($votes->hasParticipated('p1', $ada));
        self::assertFalse($votes->hasParticipated('p1', ResourceReference::of('user', 'cid')));
        self::assertFalse($votes->hasParticipated('p1u', ResourceReference::of('ser', 'ada')));
        self::assertNull($votes->ofVoter('p1', $ada));
        self::assertSame('v1', $votes->withReceipt('p1', str_repeat('a', 64))?->id);
        self::assertNull($votes->withReceipt('p2', str_repeat('a', 64)));
        self::assertNull($votes->withReceipt('p1', str_repeat('c', 64)));
        self::assertNull($votes->withReceipt('p1', str_repeat('a', 64))->voter());

        try {
            $votes->add(Vote::anonymous('v4', 'p1', str_repeat('d', 64), Ballot::of(['a'])), Participation::of('p1', $ada));
            self::fail('Accepted.');
        } catch (AlreadyVoted $alreadyVoted) {
            self::assertSame('A ballot was already cast by this voter in the poll "p1".', $alreadyVoted->getMessage());
        }

        $votes = $this->stores()[1];
        $votes->add(Vote::anonymous('v1', 'p1', str_repeat('a', 64), Ballot::of(['b'])), Participation::of('p1', $ada));
        $votes->add(Vote::anonymous('v3', 'p2', str_repeat('c', 64), Ballot::of(['a'])), Participation::of('p2', $ada));
        $votes->add(Vote::named('v5', 'p1', ResourceReference::of('user', 'bob'), Ballot::of(['a']), new DateTimeImmutable()));
        $votes->removeAllOf('p1');

        self::assertSame([0, false, 1, true], [$votes->count('p1'), $votes->hasParticipated('p1', $ada), $votes->count('p2'), $votes->hasParticipated('p2', $ada)]);
        self::assertSame([['a']], array_map(static fn (Ballot $ballot): array => $ballot->choices, iterator_to_array($votes->ballots('p2'), false)));
    }

    protected function poll(string $id, string $createdAt, ?ResourceReference $subject): Poll
    {
        return Poll::create(
            $id,
            'Which?',
            [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')],
            PollRules::ranked(['a', 'b', 'c'], 1, 2, true),
            'schulze',
            true,
            ResultsVisibility::AfterVoting,
            ResourceReference::of('user', 'ada'),
            new DateTimeImmutable($createdAt.' UTC'),
            $subject,
        );
    }
}
