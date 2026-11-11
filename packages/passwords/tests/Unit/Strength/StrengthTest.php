<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\CrackTimes;
use Phil\Passwords\Strength\Feedback;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\Strength;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Strength::class)]
final class StrengthTest extends TestCase
{
    public function testTheOrderOfMagnitudeAndTheTruncation(): void
    {
        $strength = new Strength(1000.0, Score::Weak, [], CrackTimes::fromGuesses(1000.0), new Feedback(), 10, 10);

        self::assertSame(3.0, $strength->guessesLog10());
        self::assertFalse($strength->isTruncated());
        self::assertTrue(new Strength(1.0, Score::VeryWeak, [], CrackTimes::fromGuesses(1.0), new Feedback(), 11, 10)->isTruncated());
    }
}
