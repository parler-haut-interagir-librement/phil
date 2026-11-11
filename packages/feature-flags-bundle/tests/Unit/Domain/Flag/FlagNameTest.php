<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagName::class)]
final class FlagNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validNames(): iterable
    {
        yield 'single segment' => ['checkout'];
        yield 'dotted' => ['billing.new-checkout'];
        yield 'underscored with digits' => ['v2_search'];
    }

    #[DataProvider('validNames')]
    public function testAValidNameIsKept(string $name): void
    {
        self::assertSame($name, FlagName::of($name)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Checkout'];
        yield 'leading separator' => ['.checkout'];
        yield 'trailing separator' => ['checkout-'];
        yield 'doubled separator' => ['new--checkout'];
        yield 'space' => ['new checkout'];
        yield 'non-ASCII' => ['caisse-é'];
        yield 'trailing newline' => ["checkout\n"];
    }

    #[DataProvider('invalidNames')]
    public function testAnInvalidNameIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);

        FlagName::of($name);
    }

    public function testTheMaximumLengthIsAccepted(): void
    {
        self::assertSame(100, \strlen(FlagName::of(str_repeat('a', 100))->value));
    }

    public function testOneCharacterOverTheMaximumIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A flag name is at most 100 characters; this one has 101.');

        FlagName::of(str_repeat('a', 101));
    }

    public function testEqualityIsByValue(): void
    {
        self::assertTrue(FlagName::of('a.b')->equals(FlagName::of('a.b')));
        self::assertFalse(FlagName::of('a.b')->equals(FlagName::of('a.c')));
    }
}
