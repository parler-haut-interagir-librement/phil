<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit;

use InvalidArgumentException;
use Phil\CommunityNotes\PairHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PairHistory::class)]
final class PairHistoryTest extends TestCase
{
    public function testTheShareOfAgreementIsComparedInIntegers(): void
    {
        $history = PairHistory::of(4, 1);

        self::assertSame([4, 1], [$history->shared, $history->agreed]);
        self::assertTrue($history->agreesLessThan(1, 2));
        self::assertFalse($history->agreesLessThan(1, 4));
        self::assertTrue($history->agreesLessThan(26, 100));
        self::assertFalse(PairHistory::of(2, 1)->agreesLessThan(1, 2));
        self::assertFalse(PairHistory::of(0, 0)->agreesLessThan(1, 2));
        self::assertSame(0, PairHistory::of(0, 0)->shared);
    }

    public function testImpossibleCountsAreRefused(): void
    {
        foreach ([[1, 2, 'Two raters agree on 0 to 1 of the notes they share, not 2.'], [3, -1, 'Two raters agree on 0 to 3 of the notes they share, not -1.'], [-2, -3, 'Two raters agree on 0 to 0 of the notes they share, not -3.']] as [$shared, $agreed, $message]) {
            try {
                PairHistory::of($shared, $agreed);
                self::fail($message);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }
        }
    }
}
