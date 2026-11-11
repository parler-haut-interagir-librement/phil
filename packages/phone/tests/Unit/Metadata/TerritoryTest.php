<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Unit\Metadata;

use Phil\Phone\Metadata\NumberDesc;
use Phil\Phone\Metadata\NumberFormat;
use Phil\Phone\Metadata\Territory;
use Phil\Phone\PhoneNumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Territory::class)]
#[CoversClass(NumberDesc::class)]
#[CoversClass(NumberFormat::class)]
final class TerritoryTest extends TestCase
{
    /**
     * @param array<string, NumberDesc> $types
     */
    private static function territory(?string $prefixForParsing = null, ?string $transform = null, string $general = '[1-9]\d{3}', array $types = [], ?string $internationalPrefix = '00', ?string $leadingDigits = null): Territory
    {
        return new Territory('ZZ', 999, $internationalPrefix, '0', $prefixForParsing, $transform, $leadingDigits, null, new NumberDesc($general, [4]), $types, []);
    }

    /**
     * @return iterable<string, array{Territory, string, string}>
     */
    public static function prefixes(): iterable
    {
        yield 'no prefix to parse' => [self::territory(), '01234', '01234'];
        yield 'a prefix stripped' => [self::territory('0'), '01234', '1234'];
        yield 'a prefix absent' => [self::territory('0'), '1234', '1234'];
        yield 'an empty match' => [self::territory('0?'), '1234', '1234'];
        yield 'a viable number kept' => [self::territory('1'), '1234', '1234'];
        yield 'a prefix transformed' => [self::territory('0(2)', '5$1'), '02234', '52234'];
        yield 'a prefix transformed with several groups' => [self::territory('(0)(2)', '5$2'), '02234', '52234'];
        yield 'a missing group in the rule' => [self::territory('0(2)', '5$3'), '02234', '5234'];
        yield 'a prefix whose group is absent' => [self::territory('0(2)?', '5$1'), '01234', '1234'];
        yield 'a prefix without groups' => [self::territory('0', '5$1'), '01234', '1234'];
    }

    #[DataProvider('prefixes')]
    public function testANationalPrefixIsTakenAway(Territory $territory, string $digits, string $national): void
    {
        self::assertSame($national, $territory->withoutNationalPrefix($digits));
    }

    public function testAnInternationalPrefixIsRecognised(): void
    {
        self::assertSame('33612', self::territory()->dialledInternationally('0033612'));
        self::assertNull(self::territory()->dialledInternationally('00'));
        self::assertNull(self::territory()->dialledInternationally('000612'));
        self::assertNull(self::territory()->dialledInternationally('1233612'));
        self::assertNull(self::territory()->dialledInternationally('x0033612'));
        self::assertNull(self::territory(internationalPrefix: null)->dialledInternationally('0033612'));
    }

    public function testATerritoryClaimsTheNumbersOfItsLeadingDigits(): void
    {
        self::assertTrue(self::territory(leadingDigits: '12')->claims('1234'));
        self::assertFalse(self::territory(leadingDigits: '12')->claims('3124'));
        self::assertFalse(self::territory()->claims('1234'));
    }

    public function testLandlinesAndMobilesSharingAPatternAreEither(): void
    {
        $same = self::territory(types: ['fixedLine' => new NumberDesc('1\d{3}', [4]), 'mobile' => new NumberDesc('1\d{3}', [4])]);
        $fixedOnly = self::territory(types: ['fixedLine' => new NumberDesc('1\d{3}', [4])]);
        $both = self::territory(types: ['fixedLine' => new NumberDesc('1\d{3}', [4]), 'mobile' => new NumberDesc('[12]\d{3}', [4])]);

        self::assertSame(PhoneNumberType::FixedLineOrMobile, $same->typeOf('1234'));
        self::assertSame(PhoneNumberType::FixedLine, $fixedOnly->typeOf('1234'));
        self::assertSame(PhoneNumberType::FixedLineOrMobile, $both->typeOf('1234'));
        self::assertSame(PhoneNumberType::Mobile, $both->typeOf('2234'));
        self::assertSame(PhoneNumberType::Unknown, $both->typeOf('3234'));
        self::assertSame(PhoneNumberType::Unknown, $both->typeOf('12345'));
        self::assertSame(PhoneNumberType::Unknown, self::territory(types: ['fixedLine' => new NumberDesc('0\d{3}', [4])])->typeOf('0234'));
    }

    public function testADescriptionChecksTheLengthAndTheWholeNumber(): void
    {
        $desc = new NumberDesc('1\d{2}', [3, 5]);

        self::assertTrue($desc->matches('123'));
        self::assertFalse($desc->matches('1234'));
        self::assertFalse($desc->matches('12345'));
        self::assertFalse($desc->matches('0123'));
        self::assertFalse(new NumberDesc('\d{3}', [4])->matches('123'));
    }

    public function testAFormatAppliesToTheDigitsItStartsWith(): void
    {
        $format = new NumberFormat('(\d{2})(\d{2})', '1', '$1 $2', null, '$1 $2');

        self::assertTrue($format->applies('1234'));
        self::assertFalse($format->applies('2234'));
        self::assertFalse($format->applies('12345'));
        self::assertFalse($format->applies('x1234'));
        self::assertTrue(new NumberFormat('(\d{2})(\d{2})', null, '$1 $2', null, null)->applies('2234'));
        self::assertSame('12-34', $format->apply('1234', '$1-$2'));
    }
}
