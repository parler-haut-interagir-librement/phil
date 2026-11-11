<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatternMatch::class)]
final class PatternMatchTest extends TestCase
{
    public function testLengthSubstitutionsAndGuesses(): void
    {
        $match = new PatternMatch(Pattern::Dictionary, 2, 5, 'p@ss', 4.0, substitutions: ['@' => 'a']);

        self::assertSame(4, $match->length());
        self::assertTrue($match->isL33t());
        self::assertFalse(new PatternMatch(Pattern::Dictionary, 0, 0, 'a', 1.0)->isL33t());

        $floored = $match->withGuesses(50.0);
        self::assertSame(50.0, $floored->guesses);
        self::assertSame(4.0, $match->guesses);
        self::assertSame(['@' => 'a'], $floored->substitutions);
    }

    public function testTheDefaultsOfTheFieldsOfOtherPatterns(): void
    {
        $match = new PatternMatch(Pattern::Bruteforce, 0, 2, 'x7k', 1000.0);

        self::assertSame(
            [null, 0, null, false, [], null, 0, 0, 0, null, 0, 0, 0, 0, ''],
            [$match->dictionary, $match->rank, $match->word, $match->reversed, $match->substitutions, $match->layout, $match->turns, $match->shifted, $match->step, $match->baseToken, $match->repeatCount, $match->year, $match->month, $match->day, $match->separator],
        );
    }
}
