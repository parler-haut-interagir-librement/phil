<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\Guesses;
use Phil\Passwords\Strength\Internal\SpatialMatcher;
use Phil\Passwords\Strength\KeyboardLayout;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialMatcher::class)]
final class SpatialMatcherTest extends TestCase
{
    public function testAStraightRow(): void
    {
        $layout = KeyboardLayout::qwerty();
        [$match] = new SpatialMatcher([$layout])->matches(mb_str_split('xqwertyx'));

        self::assertSame(Pattern::Spatial, $match->pattern);
        self::assertSame([1, 6, 'qwerty', 'qwerty', 1, 0], [$match->start, $match->end, $match->token, $match->layout, $match->turns, $match->shifted]);
        // Five lengths from 2 to 6, one turn each: s × d.
        self::assertEqualsWithDelta(5 * $layout->keys * $layout->averageDegree, $match->guesses, 1e-9);
    }

    public function testTurnsAndShiftedKeys(): void
    {
        $layout = KeyboardLayout::qwerty();
        $matches = new SpatialMatcher([$layout])->matches(mb_str_split('zxcvfr QWE'));

        self::assertSame(['zxcvfr', 'QWE'], array_map(static fn (PatternMatch $match): string => $match->token, $matches));
        self::assertSame([2, 0], [$matches[0]->turns, $matches[0]->shifted]);
        self::assertSame([7, 9, 1, 3], [$matches[1]->start, $matches[1]->end, $matches[1]->turns, $matches[1]->shifted]);

        $s = $layout->keys;
        $d = $layout->averageDegree;
        $twoTurns = 5 * $s * $d + (2 + 3 + 4 + 5) * $s * $d ** 2;
        self::assertEqualsWithDelta($twoTurns, $matches[0]->guesses, 1e-6);
        // All three keys shifted: twice the walks.
        self::assertEqualsWithDelta(2 * 2 * $s * $d, $matches[1]->guesses, 1e-9);
    }

    public function testSomeShiftedKeysAndAShiftedStart(): void
    {
        $layout = KeyboardLayout::qwerty();
        [$first] = new SpatialMatcher([$layout])->matches(mb_str_split('Qwer'));
        [$second] = new SpatialMatcher([$layout])->matches(mb_str_split('qwEr'));

        self::assertSame(1, $first->shifted);
        self::assertSame(1, $second->shifted);
        self::assertEqualsWithDelta(3 * $layout->keys * $layout->averageDegree * Guesses::variations(1, 3), $second->guesses, 1e-9);
    }

    public function testRunsShorterThanThreeKeysAreNotPatterns(): void
    {
        self::assertSame([], new SpatialMatcher([KeyboardLayout::qwerty()])->matches(mb_str_split('qwxpalx')));
        self::assertSame([], new SpatialMatcher([KeyboardLayout::qwerty()])->matches(mb_str_split('qw')));
    }

    public function testAPatternAtTheEndAndOneRightAfterAnother(): void
    {
        $matches = new SpatialMatcher([KeyboardLayout::qwerty()])->matches(mb_str_split('asdfp;/'));

        self::assertSame([[0, 3], [4, 6]], array_map(static fn (PatternMatch $match): array => [$match->start, $match->end], $matches));
    }

    public function testEachLayout(): void
    {
        $layouts = static fn (string $password): array => array_map(
            static fn (PatternMatch $match): ?string => $match->layout,
            new SpatialMatcher(KeyboardLayout::bundled())->matches(mb_str_split($password)),
        );

        self::assertSame(['qwerty', 'azerty', 'qwertz'], $layouts('ghjk'));
        self::assertSame(['azerty'], $layouts('wxwx'));
        self::assertSame(['qwertz'], $layouts('yxyx'));
        self::assertSame(['keypad'], $layouts('7415'));
    }
}
