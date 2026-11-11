<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Hyphenation;

use Phil\Typography\Hyphenation\Patterns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
final class PatternsTest extends TestCase
{
    public function testItReadsPatternsAndExceptions(): void
    {
        $patterns = Patterns::parse(".ach4 a1b2c\n\n 1hy3ph é1t ", "ta-ble Pro-ject\n present", 3, 4);

        self::assertSame(['.ach' => [4 => 4], 'abc' => [1 => 1, 2 => 2], 'hyph' => [0 => 1, 2 => 3], 'ét' => [1 => 1]], $patterns->patterns);
        self::assertSame(4, $patterns->longest);
        self::assertSame(['table' => [2], 'project' => [3], 'present' => []], $patterns->exceptions);
        self::assertSame(3, $patterns->left);
        self::assertSame(4, $patterns->right);
    }

    public function testExceptionsCountCharactersAndAreKeptInLowerCase(): void
    {
        self::assertSame(['été' => [1], 'ééé' => [2]], Patterns::parse('', 'É-TÉ éé-é')->exceptions);
    }

    public function testTheDefaults(): void
    {
        $patterns = Patterns::parse('');

        self::assertSame([], $patterns->patterns);
        self::assertSame(0, $patterns->longest);
        self::assertSame([], $patterns->exceptions);
        self::assertSame(2, $patterns->left);
        self::assertSame(2, $patterns->right);
    }

    public function testAtLeastOneLetterIsKeptAtEachEnd(): void
    {
        $patterns = Patterns::parse('a1b', '', 0, -1);

        self::assertSame(1, $patterns->left);
        self::assertSame(1, $patterns->right);
    }

    public function testTheLongestCountsLettersNotBytes(): void
    {
        self::assertSame(3, Patterns::parse('é1é2é ab')->longest);
        self::assertSame(3, Patterns::parse('abc 1é')->longest);
    }
}
