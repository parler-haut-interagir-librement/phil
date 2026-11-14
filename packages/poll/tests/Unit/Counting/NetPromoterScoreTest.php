<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\BallotKind;
use Phil\Poll\Counting\Marks;
use Phil\Poll\Counting\NetPromoterScore;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\NetPromoter;
use Phil\Poll\PollRules;
use Phil\Poll\Rating;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetPromoterScore::class)]
#[CoversClass(Marks::class)]
#[CoversClass(NetPromoter::class)]
final class NetPromoterScoreTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new NetPromoterScore();

        self::assertSame('nps', $method->name());
        self::assertTrue($method->supports(BallotKind::Scale));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
        self::assertFalse($method->supports(BallotKind::Ranked));
    }

    public function testTheGroupsAtTheirBounds(): void
    {
        $ballots = Ballots::of([[1, ['0']], [1, ['6']], [1, ['7']], [1, ['8']], [1, ['9']], [3, ['10']], [1, []]]);
        $result = new NetPromoterScore()->count(PollRules::netPromoter(true), $ballots);

        self::assertSame(['nps', 9, 1], [$result->method, $result->ballots, $result->abstentions]);
        self::assertEquals(new NetPromoter(4, 2, 2), $result->netPromoter);
        self::assertEquals(new Rating(8, 60), $result->rating);
        self::assertSame(['10'], $result->winners());
        self::assertSame(['10', 3, 1], Ballots::standings($result)[0]);
        $groups = $result->netPromoter;
        self::assertInstanceOf(NetPromoter::class, $groups);
        self::assertSame(8, $groups->respondents());
        self::assertSame(25.0, $groups->score());
    }

    public function testTheScoreRunsFromMinusAHundredToAHundred(): void
    {
        self::assertSame(-100.0, new NetPromoterScore()->count(PollRules::netPromoter(), Ballots::of([[2, ['6']]]))->netPromoter?->score());
        self::assertSame(100.0, new NetPromoterScore()->count(PollRules::netPromoter(), Ballots::of([[2, ['9']]]))->netPromoter?->score());
        self::assertSame(0.0, new NetPromoterScore()->count(PollRules::netPromoter(), Ballots::of([[2, ['8']]]))->netPromoter?->score());
        self::assertEqualsWithDelta(100 / 6, new NetPromoterScore()->count(PollRules::netPromoter(), Ballots::of([[2, ['9']], [1, ['0']], [3, ['7']]]))->netPromoter?->score(), 1e-12);
    }

    public function testNoMarkHasNoScore(): void
    {
        $result = new NetPromoterScore()->count(PollRules::netPromoter(true), Ballots::of([[1, []]]));

        self::assertEquals(new NetPromoter(0, 0, 0), $result->netPromoter);
        self::assertNull($result->netPromoter?->score());
        self::assertSame(0, $result->netPromoter?->respondents());
    }

    public function testOnlyAScaleFromZeroToTenIsCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('A Net Promoter Score counts the marks of a scale from 0 to 10.'));

        new NetPromoterScore()->count(PollRules::scale(1, 10), []);
    }

    public function testRulesOfAnotherKindAreRefused(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('A Net Promoter Score counts the marks of a scale from 0 to 10.'));

        new NetPromoterScore()->count(PollRules::single(['a', 'b']), []);
    }

    public function testASingleBallotOnTheSameOptionsIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The Net Promoter Score counts the marks of a scale only.'));

        new NetPromoterScore()->count(PollRules::of(BallotKind::Single, PollRules::netPromoter()->options, 1, 1), []);
    }
}
