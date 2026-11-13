<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Poll\Ballot;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Vote::class)]
#[CoversClass(Participation::class)]
#[CoversClass(AlreadyVoted::class)]
final class VoteTest extends TestCase
{
    public function testANamedBallot(): void
    {
        $vote = Vote::named('v1', 'p1', ResourceReference::of('user', 'ada'), Ballot::of(['b', 'a']), new DateTimeImmutable('2026-11-08 10:00:00.6 UTC'));

        self::assertSame(['v1', 'p1'], [$vote->id, $vote->poll]);
        self::assertSame(['b', 'a'], $vote->ballot()->choices);
        self::assertSame('user:ada', $vote->voter()?->toString());
        self::assertFalse($vote->isAnonymous());
        self::assertNull($vote->receiptHash());
        self::assertSame('2026-11-08 10:00:00.000000 +00:00', $vote->castAt()?->format('Y-m-d H:i:s.u P'));

        $vote->change(Ballot::of(['a']), new DateTimeImmutable('2026-11-08 11:00:00.2 UTC'));

        self::assertSame(['a'], $vote->ballot()->choices);
        self::assertSame('2026-11-08 11:00:00.000000', $vote->castAt()?->format('Y-m-d H:i:s.u'));
    }

    public function testAnAnonymousBallot(): void
    {
        $vote = Vote::anonymous('v2', 'p1', 'hash', Ballot::of([]));

        self::assertTrue($vote->isAnonymous());
        self::assertNull($vote->voter());
        self::assertSame('hash', $vote->receiptHash());
        self::assertNull($vote->castAt());
        self::assertTrue($vote->ballot()->isAbstention());

        $vote->change(Ballot::of(['c']), new DateTimeImmutable());

        self::assertSame(['c'], $vote->ballot()->choices);
        self::assertNull($vote->castAt());
    }

    public function testAParticipation(): void
    {
        $participation = Participation::of('p1', ResourceReference::of('user', 'ada'));

        self::assertSame('p1', $participation->poll);
        self::assertSame('user:ada', $participation->voter()->toString());
        self::assertSame('A ballot was already cast by this voter in the poll "p1".', AlreadyVoted::in('p1')->getMessage());
    }
}
