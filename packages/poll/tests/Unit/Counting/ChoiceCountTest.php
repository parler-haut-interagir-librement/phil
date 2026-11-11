<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\Choices;
use Phil\Poll\Counting\Places;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Standing;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChoiceCount::class)]
#[CoversClass(Choices::class)]
#[CoversClass(Places::class)]
final class ChoiceCountTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new ChoiceCount();

        self::assertSame('choices', $method->name());
        self::assertTrue($method->supports(BallotKind::Single));
        self::assertTrue($method->supports(BallotKind::Multiple));
        self::assertFalse($method->supports(BallotKind::Ranked));
    }

    public function testASingleBallotIsAPlurality(): void
    {
        $result = new ChoiceCount()->count(PollRules::single(['a', 'b', 'c'], true), Ballots::of([[2, ['b']], [1, []], [3, ['c']]]));

        self::assertSame(['choices', 6, 1], [$result->method, $result->ballots, $result->abstentions]);
        self::assertSame([['c', 3, 1], ['b', 2, 2], ['a', 0, 3]], Ballots::standings($result));
        self::assertSame(['c'], $result->winners());
        self::assertSame([], $result->rounds);
    }

    public function testAMultipleBallotIsAnApprovalCount(): void
    {
        $result = new ChoiceCount()->count(PollRules::multiple(['a', 'b', 'c', 'd']), Ballots::of([[1, ['a', 'b']], [1, ['b']], [1, ['c', 'b']]]));

        self::assertSame([['b', 3, 1], ['a', 1, 2], ['c', 1, 2], ['d', 0, 4]], Ballots::standings($result));
    }

    public function testTiedOptionsShareTheirPlaceInTheDeclaredOrder(): void
    {
        $result = new ChoiceCount()->count(PollRules::single(['z', 'y', 'x']), Ballots::of([[1, ['x']], [1, ['y']], [1, ['z']]]));

        self::assertSame([['z', 1, 1], ['y', 1, 1], ['x', 1, 1]], Ballots::standings($result));
        self::assertSame(['z', 'y', 'x'], $result->winners());
    }

    public function testNoBallot(): void
    {
        $result = new ChoiceCount()->count(PollRules::single(['a', 'b']), []);

        self::assertSame([0, 0, []], [$result->ballots, $result->abstentions, $result->winners()]);
        self::assertSame([['a', 0, 1], ['b', 0, 1]], Ballots::standings($result));
    }

    public function testRankedBallotsAreNotCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The count of choices does not count ranked ballots.'));

        new ChoiceCount()->count(PollRules::ranked(['a', 'b']), []);
    }

    public function testAnUnknownChoiceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('"c" is not an option of this poll.'));

        new ChoiceCount()->count(PollRules::single(['a', 'b']), [Ballot::of(['a']), Ballot::of(['c'])]);
    }

    public function testAChoiceGivenTwiceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('The option "b" is chosen twice.'));

        new ChoiceCount()->count(PollRules::multiple(['a', 'b']), [Ballot::of(['b', 'b'])]);
    }

    public function testThePlacesOfAKeyAndAScore(): void
    {
        $standings = Places::of(['a', 'b', 'c', 'd'], [1, 3, 2, 0], [1, 1, 0, 1]);

        self::assertSame([['c', 2, 1], ['b', 3, 2], ['a', 1, 2], ['d', 0, 2]], array_map(static fn (Standing $standing): array => [$standing->option, $standing->score, $standing->place], $standings));
        self::assertSame([], Places::of([], [], []));
    }
}
