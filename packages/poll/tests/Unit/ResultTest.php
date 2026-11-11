<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit;

use Phil\Poll\Result;
use Phil\Poll\Round;
use Phil\Poll\Standing;
use Phil\Poll\Tally;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Result::class)]
#[CoversClass(Standing::class)]
#[CoversClass(Round::class)]
#[CoversClass(Tally::class)]
final class ResultTest extends TestCase
{
    public function testTheWinnersAreTheFirstPlaces(): void
    {
        $round = new Round([new Tally('a', 2)], 1, ['b']);
        $result = new Result('m', 4, 1, [new Standing('a', 2, 1), new Standing('c', 2, 1), new Standing('b', 1, 3)], [$round]);

        self::assertSame(['m', 4, 1, [$round]], [$result->method, $result->ballots, $result->abstentions, $result->rounds]);
        self::assertEquals([[new Tally('a', 2)], 1, ['b']], [$round->tallies, $round->exhausted, $round->eliminated]);
        self::assertSame('a', $round->tallies[0]->option);
        self::assertSame(2, $round->tallies[0]->votes);
        self::assertSame(['a', 'c'], $result->winners());
        self::assertSame(1, $result->standingOf('b')?->score);
        self::assertSame(3, $result->standingOf('b')->place);
        self::assertSame('c', $result->standingOf('c')?->option);
        self::assertNull($result->standingOf('z'));
        self::assertSame([], new Result('m', 0, 0, [])->rounds);
    }

    public function testNobodyWinsWithoutAChoice(): void
    {
        self::assertSame([], new Result('m', 3, 3, [new Standing('a', 0, 1), new Standing('b', 0, 1)])->winners());
        self::assertSame([], new Result('m', 0, 0, [new Standing('a', 0, 1)])->winners());
        self::assertSame(['a'], new Result('m', 1, 0, [new Standing('a', 1, 1), new Standing('b', 0, 2)])->winners());
    }
}
