<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator;

use Phil\Passwords\Exception\InvalidPolicy;
use Phil\Passwords\Generator\CharacterSet;
use Phil\Passwords\Generator\PasswordPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordPolicy::class)]
#[CoversClass(CharacterSet::class)]
final class PasswordPolicyTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $policy = new PasswordPolicy();

        self::assertSame(20, $policy->length);
        self::assertSame([CharacterSet::Lowercase, CharacterSet::Uppercase, CharacterSet::Digits, CharacterSet::Symbols], $policy->sets);
        self::assertFalse($policy->excludeAmbiguous);
        self::assertTrue($policy->requireEachSet);
        self::assertSame(
            ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789', '!#$%&()*+,-./:;<=>?@[]^_{|}~'],
            $policy->alphabets(),
        );
    }

    public function testWithers(): void
    {
        $policy = new PasswordPolicy()->withLength(8)->withSets(CharacterSet::Digits, CharacterSet::Uppercase, CharacterSet::Digits)->withoutAmbiguous()->withEachSetRequired(false);

        self::assertSame(8, $policy->length);
        self::assertSame([CharacterSet::Digits, CharacterSet::Uppercase], $policy->sets);
        self::assertTrue($policy->excludeAmbiguous);
        self::assertFalse($policy->requireEachSet);
        self::assertSame(['23456789', 'ABCDEFGHJKLMNPQRSTUVWXYZ'], $policy->alphabets());
        self::assertFalse($policy->withoutAmbiguous(false)->excludeAmbiguous);
        self::assertTrue($policy->withEachSetRequired()->requireEachSet);
        self::assertSame(['abcdefghijkmnpqrstuvwxyz'], $policy->withSets(CharacterSet::Lowercase)->alphabets());
        self::assertSame(['!#$%&()*+,-./:;<=>?@[]^_{}~'], $policy->withSets(CharacterSet::Symbols)->alphabets());
    }

    public function testTheBoundsOfTheLength(): void
    {
        self::assertSame(4, new PasswordPolicy(4)->length);
        self::assertSame(512, new PasswordPolicy(512)->length);

        foreach ([3, 513] as $length) {
            try {
                new PasswordPolicy($length);
                self::fail('Length '.$length);
            } catch (InvalidPolicy $exception) {
                self::assertSame('A generated password has 4 to 512 characters.', $exception->getMessage());
            }
        }
    }

    public function testASetIsRequired(): void
    {
        $this->expectExceptionObject(new InvalidPolicy('A generated password draws from at least one character set.'));

        new PasswordPolicy(sets: []);
    }

    public function testEntropy(): void
    {
        // Ten digits, eight of them: 10^8 passwords.
        self::assertEqualsWithDelta(8 * log(10, 2), new PasswordPolicy(8, [CharacterSet::Digits])->entropyBits(), 1e-9);
        self::assertEqualsWithDelta(8 * log(36, 2), new PasswordPolicy(8, [CharacterSet::Digits, CharacterSet::Lowercase], requireEachSet: false)->entropyBits(), 1e-9);
        // Each set required: 36^8 - 26^8 - 10^8 passwords hold a digit and a letter.
        self::assertEqualsWithDelta(log(36 ** 8 - 26 ** 8 - 10 ** 8, 2), new PasswordPolicy(8, [CharacterSet::Digits, CharacterSet::Lowercase])->entropyBits(), 1e-9);
        // Three sets: inclusion and exclusion over the subsets.
        $expected = log(62 ** 6 - 36 ** 6 - 36 ** 6 - 52 ** 6 + 26 ** 6 + 26 ** 6 + 10 ** 6, 2);
        self::assertEqualsWithDelta($expected, new PasswordPolicy(6, [CharacterSet::Digits, CharacterSet::Lowercase, CharacterSet::Uppercase])->entropyBits(), 1e-9);
        // An odd length: every term keeps its sign.
        self::assertEqualsWithDelta(log(36 ** 7 - 26 ** 7 - 10 ** 7, 2), new PasswordPolicy(7, [CharacterSet::Digits, CharacterSet::Lowercase])->entropyBits(), 1e-9);
        // Long passwords stay in range.
        self::assertEqualsWithDelta(512 * log(90, 2), new PasswordPolicy(512)->entropyBits(), 1e-6);
    }

    public function testRepeatedSetsMakeAList(): void
    {
        self::assertSame(
            [CharacterSet::Digits, CharacterSet::Uppercase],
            new PasswordPolicy(sets: [CharacterSet::Digits, CharacterSet::Digits, CharacterSet::Uppercase])->sets,
        );
    }
}
