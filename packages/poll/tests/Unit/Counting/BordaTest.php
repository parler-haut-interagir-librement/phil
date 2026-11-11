<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Borda::class)]
final class BordaTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new Borda();

        self::assertSame('borda', $method->name());
        self::assertTrue($method->supports(BallotKind::Ranked));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
    }

    public function testRanksArePoints(): void
    {
        $result = new Borda()->count(PollRules::ranked(['a', 'b', 'c'], 1, 3, true), Ballots::of([[1, ['a', 'b', 'c']], [1, ['b']], [1, ['c', 'a']], [2, []]]));

        self::assertSame(['borda', 5, 2], [$result->method, $result->ballots, $result->abstentions]);
        self::assertSame([['a', 3, 1], ['b', 3, 1], ['c', 2, 3]], Ballots::standings($result));
    }

    public function testFourOptions(): void
    {
        $result = new Borda()->count(PollRules::ranked(['a', 'b', 'c', 'd']), Ballots::of([[2, ['d', 'c', 'b', 'a']], [1, ['a']]]));

        self::assertSame([['d', 6, 1], ['c', 4, 2], ['a', 3, 3], ['b', 2, 4]], Ballots::standings($result));
    }

    public function testOnlyRankedBallotsAreCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The Borda count counts ranked ballots only.'));

        new Borda()->count(PollRules::multiple(['a', 'b']), []);
    }

    public function testAnUnknownChoiceIsRefused(): void
    {
        $this->expectException(InvalidBallot::class);

        new Borda()->count(PollRules::ranked(['a', 'b']), [Ballot::of(['x'])]);
    }
}
