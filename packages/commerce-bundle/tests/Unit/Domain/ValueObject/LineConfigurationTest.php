<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineConfiguration::class)]
#[CoversClass(OptionValue::class)]
#[CoversClass(SellableReference::class)]
final class LineConfigurationTest extends TestCase
{
    public function testAChosenValueIsShownAsChosenOrAsItsDisplay(): void
    {
        $value = OptionValue::chosen('Bob');

        self::assertSame('Bob', $value->value);
        self::assertNull($value->label);
        self::assertFalse($value->isToDraw());
        self::assertFalse($value->isPending());
        self::assertSame('Bob', $value->shown());
        self::assertSame('Bob', $value->described('Name')->shown());
        self::assertSame('Marble', OptionValue::chosen('marble')->described('Stone', 'Marble')->shown());
        self::assertSame('Name', $value->described('Name')->label);
    }

    public function testAValueToDrawIsPendingUntilItsDrawsAreMade(): void
    {
        $value = OptionValue::toDraw()->described('Loot', null, 'tables');

        self::assertNull($value->value);
        self::assertTrue($value->isToDraw());
        self::assertTrue($value->isPending());
        self::assertNull($value->shown());
        self::assertSame([], $value->draws);

        $drawn = $value->withDraws(['352614', '111111']);
        self::assertTrue($drawn->isToDraw());
        self::assertFalse($drawn->isPending());
        self::assertSame(['352614', '111111'], $drawn->draws);
        self::assertSame('352614, 111111', $drawn->shown());
        self::assertSame(['Loot', 'tables'], [$drawn->label, $drawn->drawer]);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'a blank value' => [static fn (): mixed => OptionValue::chosen(' '), 'A chosen value is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'a value of broken UTF-8' => [static fn (): mixed => OptionValue::chosen("\xC3"), 'A chosen value is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'a value too long' => [static fn (): mixed => OptionValue::chosen(str_repeat('é', 1001)), 'A chosen value is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'a blank label' => [static fn (): mixed => OptionValue::chosen('a')->described(' '), "A described option value has its option's label."];
        yield 'a blank display' => [static fn (): mixed => OptionValue::chosen('a')->described('A', ' '), 'What a receipt shows of an option is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'a chosen value with a drawer' => [static fn (): mixed => OptionValue::chosen('a')->described('A', null, 'x'), 'A value to draw names its drawer, and a chosen value none.'];
        yield 'a value to draw without a drawer' => [static fn (): mixed => OptionValue::toDraw()->described('A'), 'A value to draw names its drawer, and a chosen value none.'];
        yield 'a drawer with a space' => [static fn (): mixed => OptionValue::toDraw()->described('A', null, 'x y'), '"x y" is not the name of a drawer: 1 to 64 visible ASCII characters.'];
        yield 'a drawer too long' => [static fn (): mixed => OptionValue::toDraw()->described('A', null, str_repeat('x', 65)), 'is not the name of a drawer'];
        yield 'a drawer with a trailing line' => [static fn (): mixed => OptionValue::toDraw()->described('A', null, "x\n"), 'is not the name of a drawer'];
        yield 'draws on a chosen value' => [static fn (): mixed => OptionValue::chosen('a')->withDraws(['1']), 'Only a described value to draw receives draws.'];
        yield 'draws before the description' => [static fn (): mixed => OptionValue::toDraw()->withDraws(['1']), 'Only a described value to draw receives draws.'];
        yield 'no draw' => [static fn (): mixed => OptionValue::toDraw()->described('A', null, 'x')->withDraws([]), 'A draw gives at least one result.'];
        yield 'a blank draw' => [static fn (): mixed => OptionValue::toDraw()->described('A', null, 'x')->withDraws(['1', ' ']), 'A draw is 1 to 1000 characters of UTF-8, not blank.'];
        yield 'an option code with a capital' => [static fn (): mixed => LineConfiguration::of(['Name' => OptionValue::chosen('a')]), '"Name" is not the code of an option: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'an option code too long' => [static fn (): mixed => LineConfiguration::of(['a'.str_repeat('b', 32) => OptionValue::chosen('a')]), 'is not the code of an option'];
        yield 'an option code with a trailing line' => [static fn (): mixed => LineConfiguration::of(["a\n" => OptionValue::chosen('a')]), 'is not the code of an option'];
        yield 'draws of an option the line lacks' => [static fn (): mixed => LineConfiguration::none()->withDraws('loot', ['1']), 'The configuration has no option loot to draw.'];
        yield 'a fingerprint too short' => [static fn (): mixed => SellableReference::of('product', 'a', '0123456789abcde'), '"0123456789abcde" is not the fingerprint of a configuration: 16 lowercase hexadecimal digits.'];
        yield 'a fingerprint in capitals' => [static fn (): mixed => SellableReference::of('product', 'a', '0123456789ABCDEF'), 'is not the fingerprint of a configuration'];
        yield 'a fingerprint too long' => [static fn (): mixed => SellableReference::of('product', 'a', 'x0123456789abcdef'), 'is not the fingerprint of a configuration'];
        yield 'a value with a trailing line' => [static fn (): mixed => SellableReference::fromValue("product:a\n"), 'is not the value of a sellable reference'];
        yield 'a value with a line break' => [static fn (): mixed => SellableReference::fromValue("product:a\nb"), 'is not the value of a sellable reference'];
        yield 'a fingerprint with a trailing line' => [static fn (): mixed => SellableReference::of('product', 'a', "0123456789abcdef\n"), 'is not the fingerprint of a configuration'];
        yield 'a value without a colon' => [static fn (): mixed => SellableReference::fromValue('product'), '"product" is not the value of a sellable reference: "type:id" or "type.fingerprint:id".'];
        yield 'a value with a short fingerprint' => [static fn (): mixed => SellableReference::fromValue('product.0123:a'), 'is not the value of a sellable reference'];
        yield 'a value without an identifier' => [static fn (): mixed => SellableReference::fromValue('product:'), 'is not the value of a sellable reference'];
        yield 'a value with a bad identifier' => [static fn (): mixed => SellableReference::fromValue('product:a b'), '"a b" is not a sellable identifier'];
        yield 'a value starting otherwise' => [static fn (): mixed => SellableReference::fromValue('Xproduct:a'), 'is not the value of a sellable reference'];
    }

    /**
     * @param callable(): mixed $make
     */
    #[DataProvider('invalidValues')]
    public function testAnInvalidValueIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheBoundsOfAValueAreAccepted(): void
    {
        self::assertSame(str_repeat('é', 1000), OptionValue::chosen(str_repeat('é', 1000))->value);
        self::assertSame(str_repeat('x', 64), OptionValue::toDraw()->described('A', null, str_repeat('x', 64))->drawer);
        self::assertSame(['a'.str_repeat('b', 31)], array_keys(LineConfiguration::of(['a'.str_repeat('b', 31) => OptionValue::chosen('a')])->values));
    }

    public function testAConfigurationIsSortedByCodeAndFingerprintedByItsChosenValues(): void
    {
        $configuration = LineConfiguration::of(['text' => OptionValue::chosen('Bob'), 'colour' => OptionValue::chosen('red')]);
        $same = LineConfiguration::of(['colour' => OptionValue::chosen('red')->described('Colour', 'Red'), 'text' => OptionValue::chosen('Bob')]);
        $other = LineConfiguration::of(['text' => OptionValue::chosen('Alice'), 'colour' => OptionValue::chosen('red')]);
        $expected = substr(hash('sha256', '[["colour","red"],["text","Bob"]]'), 0, 16);

        self::assertSame(['colour', 'text'], array_keys($configuration->values));
        self::assertSame($expected, $configuration->fingerprint());
        self::assertSame($expected, $same->fingerprint());
        self::assertNotSame($expected, $other->fingerprint());
        self::assertSame(substr(hash('sha256', '[["text","Éloï"]]'), 0, 16), LineConfiguration::of(['text' => OptionValue::chosen('Éloï')])->fingerprint());
        self::assertNull(LineConfiguration::none()->fingerprint());
        self::assertTrue(LineConfiguration::none()->isEmpty());
        self::assertFalse($configuration->isEmpty());
        self::assertSame('Bob', $configuration->value('text')?->value);
        self::assertNull($configuration->value('other'));
        self::assertSame(1, LineConfiguration::VERSION);
    }

    public function testDrawsAreLeftOutOfTheFingerprint(): void
    {
        $loot = OptionValue::toDraw()->described('Loot', null, 'tables');
        $configuration = LineConfiguration::of(['text' => OptionValue::chosen('Bob'), 'loot' => $loot, 'more' => $loot]);

        self::assertSame(['loot', 'more'], $configuration->pending());
        $drawn = $configuration->withDraws('loot', ['123456']);
        self::assertSame(['more'], $drawn->pending());
        self::assertSame(['123456'], $drawn->value('loot')?->draws);
        self::assertSame('Bob', $drawn->value('text')?->value);
        self::assertSame($configuration->fingerprint(), $drawn->fingerprint());
        self::assertSame(substr(hash('sha256', '[["loot",null],["more",null],["text","Bob"]]'), 0, 16), $configuration->fingerprint());
        self::assertSame([], LineConfiguration::none()->pending());
    }

    public function testAConfiguredReferenceCarriesItsFingerprintAndReadsBack(): void
    {
        $reference = SellableReference::of('product', 'NAQ:001.a');
        $configuration = LineConfiguration::of(['text' => OptionValue::chosen('Bob')]);
        $configured = $reference->configuredBy($configuration);
        $fingerprint = (string) $configuration->fingerprint();

        self::assertNull($reference->configuration);
        self::assertSame($fingerprint, $configured->configuration);
        self::assertSame('product.'.$fingerprint.':NAQ:001.a', $configured->value());
        self::assertFalse($configured->equals($reference));
        self::assertFalse($reference->equals($configured));
        self::assertTrue($configured->equals(SellableReference::of('product', 'NAQ:001.a', $fingerprint)));
        self::assertTrue($configured->unconfigured()->equals($reference));
        self::assertNull($reference->configuredBy(LineConfiguration::none())->configuration);
        self::assertTrue(SellableReference::fromValue($configured->value())->equals($configured));
        self::assertTrue(SellableReference::fromValue('product:NAQ:001.a')->equals($reference));
        self::assertNull(SellableReference::fromValue('product:a')->configuration);
    }

    public function testTheCodeOfAnOptionIsCheckedForTheCatalogues(): void
    {
        LineConfiguration::checkCode('a'.str_repeat('b', 31));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"Bad" is not the code of an option');

        LineConfiguration::checkCode('Bad');
    }
}
