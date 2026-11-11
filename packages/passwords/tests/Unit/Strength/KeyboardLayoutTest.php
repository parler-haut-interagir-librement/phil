<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\KeyboardLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyboardLayout::class)]
final class KeyboardLayoutTest extends TestCase
{
    public function testTheStaggeredNeighboursOfQwerty(): void
    {
        $qwerty = KeyboardLayout::qwerty();

        self::assertSame('qwerty', $qwerty->name);
        self::assertSame(47, $qwerty->keys);
        self::assertSame([0, false], $qwerty->step('w', 'q'));
        self::assertSame([1, false], $qwerty->step('q', '1'));
        self::assertSame([2, false], $qwerty->step('q', '2'));
        self::assertSame([3, false], $qwerty->step('q', 'w'));
        self::assertSame([4, false], $qwerty->step('q', 'a'));
        self::assertSame([5, false], $qwerty->step('w', 'a'));
        self::assertSame([4, true], $qwerty->step('a', 'Z'));
        self::assertSame([3, true], $qwerty->step('Q', 'W'));
        self::assertNull($qwerty->step('q', '3'));
        self::assertNull($qwerty->step('q', 'é'));
        self::assertNull($qwerty->step('é', 'q'));
        self::assertTrue($qwerty->isShifted('!'));
        self::assertFalse($qwerty->isShifted('1'));
        self::assertFalse($qwerty->isShifted('é'));
    }

    public function testTheAverageDegree(): void
    {
        // `q` has four neighbours, `g` six, `p` five, `` ` `` two.
        self::assertEqualsWithDelta(216 / 47, KeyboardLayout::qwerty()->averageDegree, 1e-12);
        self::assertSame(0.0, KeyboardLayout::aligned('none', [])->averageDegree);
    }

    public function testFrenchAndGermanLayouts(): void
    {
        $azerty = KeyboardLayout::azerty();
        $qwertz = KeyboardLayout::qwertz();

        self::assertSame('azerty', $azerty->name);
        self::assertSame([5, false], $azerty->step('q', '<'));
        self::assertSame([4, false], $azerty->step('q', 'w'));
        self::assertSame([2, true], $azerty->step('a', '2'));
        self::assertSame([3, false], $azerty->step('&', 'é'));
        self::assertSame('qwertz', $qwertz->name);
        self::assertSame([3, false], $qwertz->step('t', 'z'));
        self::assertSame([4, false], $qwertz->step('a', 'y'));
    }

    public function testTheKeypad(): void
    {
        $keypad = KeyboardLayout::keypad();

        self::assertSame('keypad', $keypad->name);
        self::assertSame(15, $keypad->keys);
        self::assertSame([0, false], $keypad->step('5', '4'));
        self::assertSame([1, false], $keypad->step('5', '7'));
        self::assertSame([2, false], $keypad->step('5', '8'));
        self::assertSame([3, false], $keypad->step('5', '9'));
        self::assertSame([4, false], $keypad->step('5', '6'));
        self::assertSame([5, false], $keypad->step('5', '3'));
        self::assertSame([6, false], $keypad->step('5', '2'));
        self::assertSame([7, false], $keypad->step('5', '1'));
        self::assertNull($keypad->step('7', '_'));
        self::assertSame([3, false], $keypad->step('7', '/'));
        self::assertNull($keypad->step('4', '/'));
    }

    public function testCustomLayouts(): void
    {
        $layout = KeyboardLayout::staggered('custom', ['aA bB', 'cC dD'], [0, 1]);

        self::assertSame([4, false], $layout->step('a', 'c'));
        self::assertSame([5, true], $layout->step('b', 'C'));
        self::assertNull($layout->step('a', 'd'));
        self::assertSame(4, $layout->keys);
        self::assertSame(2.5, $layout->averageDegree);

        // Without offsets the rows are aligned: a key right below is no neighbour of a staggered keyboard.
        $aligned = KeyboardLayout::staggered('no offsets', ['a b', 'c d'], []);
        self::assertSame([3, false], $aligned->step('a', 'b'));
        self::assertNull($aligned->step('a', 'c'));
    }

    public function testBundled(): void
    {
        self::assertSame(
            ['qwerty', 'azerty', 'qwertz', 'keypad'],
            array_map(static fn (KeyboardLayout $layout): string => $layout->name, KeyboardLayout::bundled()),
        );
    }

    public function testRowsWithoutAnOffsetAreNotShifted(): void
    {
        $layout = KeyboardLayout::staggered('partial', ['a b', 'c d'], [0]);

        self::assertNull($layout->step('a', 'c'));
        self::assertNull($layout->step('b', 'c'));
    }

    public function testTheTopRowsOfQwertz(): void
    {
        $qwertz = KeyboardLayout::qwertz();

        self::assertSame([1, false], $qwertz->step('q', '1'));
        self::assertSame([2, false], $qwertz->step('q', '2'));
        self::assertSame([1, true], $qwertz->step('w', '"'));
        self::assertNull($qwertz->step('q', '3'));
    }

    public function testALaterKeyTakesARepeatedCharacter(): void
    {
        $layout = KeyboardLayout::aligned('repeated', ['a b a']);

        self::assertSame([4, false], $layout->step('b', 'a'));
        self::assertSame([0, false], $layout->step('a', 'b'));
    }
}
