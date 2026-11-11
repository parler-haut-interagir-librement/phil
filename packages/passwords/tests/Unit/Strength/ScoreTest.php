<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\Score;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Score::class)]
final class ScoreTest extends TestCase
{
    public function testTheBoundsOfEachScore(): void
    {
        self::assertSame(Score::VeryWeak, Score::fromGuesses(999.0));
        self::assertSame(Score::Weak, Score::fromGuesses(1e3));
        self::assertSame(Score::Weak, Score::fromGuesses(999999.0));
        self::assertSame(Score::Fair, Score::fromGuesses(1e6));
        self::assertSame(Score::Fair, Score::fromGuesses(99999999.0));
        self::assertSame(Score::Strong, Score::fromGuesses(1e8));
        self::assertSame(Score::Strong, Score::fromGuesses(9999999999.0));
        self::assertSame(Score::VeryStrong, Score::fromGuesses(1e10));
    }

    public function testAtLeast(): void
    {
        self::assertTrue(Score::Strong->isAtLeast(Score::Strong));
        self::assertTrue(Score::VeryStrong->isAtLeast(Score::Strong));
        self::assertFalse(Score::Fair->isAtLeast(Score::Strong));
    }
}
