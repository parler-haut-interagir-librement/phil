<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\Marks;
use Phil\Poll\Counting\ScaleCount;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Rating;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScaleCount::class)]
#[CoversClass(Marks::class)]
#[CoversClass(Rating::class)]
final class ScaleCountTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new ScaleCount();

        self::assertSame('scale', $method->name());
        self::assertTrue($method->supports(BallotKind::Scale));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
        self::assertFalse($method->supports(BallotKind::Ranked));
    }

    public function testTheDistributionAndTheSumOfTheMarks(): void
    {
        $result = new ScaleCount()->count(PollRules::scale(1, 5, true), Ballots::of([[2, ['5']], [3, ['4']], [1, []], [1, ['1']]]));

        self::assertSame(['scale', 7, 1], [$result->method, $result->ballots, $result->abstentions]);
        self::assertSame([['4', 3, 1], ['5', 2, 2], ['1', 1, 3], ['2', 0, 4], ['3', 0, 4]], Ballots::standings($result));
        self::assertSame(['4'], $result->winners());
        self::assertEquals(new Rating(6, 23), $result->rating);
        self::assertEqualsWithDelta(23 / 6, $result->rating?->mean(), 1e-12);
        self::assertNull($result->netPromoter);
        self::assertSame([], $result->rounds);
    }

    public function testNegativeMarksAddUp(): void
    {
        $result = new ScaleCount()->count(PollRules::scale(-2, 2), Ballots::of([[1, ['-2']], [1, ['-1']], [1, ['2']]]));

        self::assertEquals(new Rating(3, -1), $result->rating);
    }

    public function testNoBallotHasNoMean(): void
    {
        $result = new ScaleCount()->count(PollRules::scale(1, 3, true), Ballots::of([[2, []]]));

        self::assertSame([2, 2], [$result->ballots, $result->abstentions]);
        self::assertEquals(new Rating(0, 0), $result->rating);
        self::assertNull($result->rating?->mean());
        self::assertSame([], $result->winners());
    }

    public function testOnlyAScaleIsCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The count of a scale counts the marks of a scale only.'));

        new ScaleCount()->count(PollRules::single(['a', 'b']), []);
    }

    public function testAMarkOffTheScaleIsRefused(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('"6" is not an option of this poll.'));

        new ScaleCount()->count(PollRules::scale(1, 5), [Ballot::of(['6'])]);
    }

    public function testTwoMarksAreRefused(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('A ballot on a scale holds exactly one mark.'));

        new ScaleCount()->count(PollRules::scale(1, 5), [Ballot::of(['1']), Ballot::of(['2', '3'])]);
    }
}
