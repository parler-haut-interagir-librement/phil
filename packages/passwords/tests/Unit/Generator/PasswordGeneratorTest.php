<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator;

use Phil\Passwords\Generator\CharacterSet;
use Phil\Passwords\Generator\PasswordGenerator;
use Phil\Passwords\Generator\PasswordPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(PasswordGenerator::class)]
final class PasswordGeneratorTest extends TestCase
{
    public function testTheDefaultPolicy(): void
    {
        $password = new PasswordGenerator()->generate();

        self::assertSame(20, \strlen($password));
        self::assertMatchesRegularExpression('/[a-z]/', $password);
        self::assertMatchesRegularExpression('/[A-Z]/', $password);
        self::assertMatchesRegularExpression('/\d/', $password);
        self::assertMatchesRegularExpression('/[^a-zA-Z\d]/', $password);
    }

    public function testEachSetIsHeldInEveryPassword(): void
    {
        $generator = new PasswordGenerator(new Randomizer(new Xoshiro256StarStar(42)));
        $policy = new PasswordPolicy(4);

        for ($i = 0; $i < 200; ++$i) {
            $password = $generator->generate($policy);

            self::assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{4}$/', $password, $password);
        }
    }

    public function testWithoutEachSetAPasswordMayMissOne(): void
    {
        $generator = new PasswordGenerator(new Randomizer(new Mt19937(7)));
        $policy = new PasswordPolicy(4, requireEachSet: false);
        $missing = 0;

        for ($i = 0; $i < 200; ++$i) {
            $missing += 1 === preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])/', $generator->generate($policy)) ? 0 : 1;
        }

        self::assertGreaterThan(0, $missing);
    }

    public function testOnlyTheCharactersOfThePolicy(): void
    {
        $generator = new PasswordGenerator(new Randomizer(new Mt19937(1)));
        $password = $generator->generate(new PasswordPolicy(512, [CharacterSet::Digits, CharacterSet::Uppercase], excludeAmbiguous: true));

        self::assertSame(512, \strlen($password));
        self::assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]+$/', $password);
    }

    public function testDeterministicWithASeededRandomizer(): void
    {
        $first = new PasswordGenerator(new Randomizer(new Mt19937(3)))->generate();
        $second = new PasswordGenerator(new Randomizer(new Mt19937(3)))->generate();

        self::assertSame($first, $second);
        self::assertNotSame($first, new PasswordGenerator()->generate());
    }
}
