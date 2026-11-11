<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Unit;

use Phil\Phone\Exception\InvalidPhoneNumber;
use Phil\Phone\Metadata\NumberDesc;
use Phil\Phone\Metadata\NumberFormat;
use Phil\Phone\Metadata\Territories;
use Phil\Phone\Metadata\Territory;
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;
use Phil\Phone\PhoneNumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneNumber::class)]
#[CoversClass(InvalidPhoneNumber::class)]
#[CoversClass(Territories::class)]
#[CoversClass(Territory::class)]
#[CoversClass(NumberDesc::class)]
#[CoversClass(NumberFormat::class)]
final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function numbers(): iterable
    {
        yield 'international' => ['+1 514-555-0123', null, '+15145550123'];
        yield 'national in Canada' => ['(514) 555-0123', 'CA', '+15145550123'];
        yield 'national prefix in Canada' => ['1 800 555 0199', 'ca', '+18005550199'];
        yield 'national in France' => ['06 12 34 56 78', 'FR', '+33612345678'];
        yield 'national prefix kept in parentheses' => ['+33 (0)6 12 34 56 78', null, '+33612345678'];
        yield 'dialled from North America' => ['011 33 6 12 34 56 78', 'US', '+33612345678'];
        yield 'dialled from France' => ['00 1 514 555 0123', 'FR', '+15145550123'];
        yield 'dotted' => ['06.12.34.56.78', 'FR', '+33612345678'];
        yield 'slashed' => ['030/123456', 'DE', '+4930123456'];
        yield 'with non-breaking spaces' => ["+33\u{A0}6\u{A0}12\u{A0}34\u{A0}56\u{A0}78", null, '+33612345678'];
        yield 'with a typographic dash' => ["514\u{2011}555\u{2011}0123", 'CA', '+15145550123'];
        yield 'leading zero kept in Italy' => ['+39 06 6982 1234', null, '+390669821234'];
        yield 'national prefix transformed in Argentina' => ['011 15 2345-6789', 'AR', '+5491123456789'];
        yield 'national prefix not taken from a viable number' => ['8 800 555 35 35', 'RU', '+78005553535'];
        yield 'national prefix kept when the rest is not viable' => ['+7 800 555 35 35', null, '+78005553535'];
        yield 'numbers of no country' => ['+800 1234 5678', null, '+80012345678'];
        yield 'a three-digit country code' => ['+352 26 12 34', null, '+35226123 4'];
        yield 'an international prefix followed by zero is national' => ['011 0123456', 'US', '+10110123456'];
        yield 'a region given to an international number' => ['+44 20 7946 0958', 'FR', '+442079460958'];
        yield 'a link' => ['tel:+1-514-555-0123', null, '+15145550123'];
        yield 'surrounded by spaces' => ['  +1 514 555 0123  ', null, '+15145550123'];
        yield 'a link after spaces' => [' tel:+1-514-555-0123', null, '+15145550123'];
        yield 'a link with a space' => ['tel: +1 514 555 0123', null, '+15145550123'];
        yield 'the longest written number' => ['+1'.str_repeat("\u{A0}", 38).'5145550123', null, '+15145550123'];
        yield 'the longest national number' => ['+49 12345678901234567', null, '+4912345678901234567'];
    }

    #[DataProvider('numbers')]
    public function testANumberIsReadFromWhatPeopleType(string $input, ?string $region, string $e164): void
    {
        self::assertSame(str_replace(' ', '', $e164), PhoneNumber::parse($input, $region)->format(PhoneFormat::E164));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function extensions(): iterable
    {
        yield 'ext.' => ['+1 514 555 0123 ext. 12', '12'];
        yield 'ext' => ['+1 514 555 0123 ext 12', '12'];
        yield 'extn' => ['+1 514 555 0123 extn 12', '12'];
        yield 'extension' => ['+1 514 555 0123 Extension 12', '12'];
        yield 'x' => ['+1 514 555 0123 x12', '12'];
        yield 'poste' => ['+1 514 555 0123 poste 12', '12'];
        yield 'anexo' => ['+51 1 234 5678 anexo 12', '12'];
        yield 'int' => ['+40 21 234 5678 int 12', '12'];
        yield 'hash' => ['+1 514 555 0123 #12', '12'];
        yield 'rfc 3966' => ['tel:+1-514-555-0123;ext=1234567', '1234567'];
        yield 'rfc 3966 with a space' => ['+1 514 555 0123; ext=12', '12'];
    }

    #[DataProvider('extensions')]
    public function testAnExtensionIsReadAfterTheNumber(string $input, string $extension): void
    {
        $number = PhoneNumber::parse($input);

        self::assertSame($extension, $number->extension);
        self::assertTrue($number->isValid());
    }

    public function testANumberWithoutExtensionHasNone(): void
    {
        self::assertNull(PhoneNumber::parse('+1 514 555 0123')->extension);
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function notNumbers(): iterable
    {
        yield 'letters' => ['1-800-FLOWERS', 'US', '"1-800-FLOWERS" is not a phone number.'];
        yield 'a plus inside' => ['514+5550123', 'CA', '"514+5550123" is not a phone number.'];
        yield 'empty' => ['', 'CA', '"" is not a phone number.'];
        yield 'one digit' => ['5', 'CA', '"5" is not a phone number.'];
        yield 'too long' => ['+1 '.str_repeat('5', 18), null, '"+1 555555555555555555" is not a phone number.'];
        yield 'too long to read' => [str_repeat('5 ', 26), 'CA', '"'.str_repeat('5 ', 26).'" is not a phone number.'];
        yield 'an extension too long' => ['+1 514 555 0123 x12345678', null, '"+1 514 555 0123 x12345678" is not a phone number.'];
        yield 'a newline' => ["+1 514 555 0123\nx", null, '"+1 514 555 0123'."\n".'x" is not a phone number.'];
        yield 'no country code' => ['514 555 0123', null, '"514 555 0123" is written without its country code, and no region is given.'];
        yield 'an unknown country code' => ['+999 123456', null, '"+999 123456" starts with no known country code.'];
        yield 'a country code starting with zero' => ['+0 123456', null, '"+0 123456" starts with no known country code.'];
        yield 'an unknown region' => ['514 555 0123', 'QQ', '"QQ" is no region with a numbering plan.'];
        yield 'a region that is no code' => ['514 555 0123', '../CA', '"../CA" is no region with a numbering plan.'];
        yield 'a region of three letters' => ['514 555 0123', 'CAN', '"CAN" is no region with a numbering plan.'];
    }

    #[DataProvider('notNumbers')]
    public function testWhatIsNoNumberIsRefused(string $input, ?string $region, string $message): void
    {
        $this->expectException(InvalidPhoneNumber::class);
        $this->expectExceptionMessageIsOrContains($message);

        PhoneNumber::parse($input, $region);
    }

    public function testALongInputIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('a', 100).'" is not a phone number.');

        PhoneNumber::parse(str_repeat('a', 150));
    }

    public function testAnInputUpToItsLimitIsRead(): void
    {
        self::assertSame('+15145550123', (string) PhoneNumber::parse(str_repeat(' ', 88).'+15145550123'));
    }

    public function testTheLimitCountsCharactersNotBytes(): void
    {
        self::assertSame('+15145550123;ext=123', (string) PhoneNumber::parse('+15145550123'.str_repeat("\u{00A0}", 84).'x123'));
    }

    public function testAnInputBeyondItsLimitIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('is not a phone number.');

        PhoneNumber::parse(str_repeat(' ', 89).'+15145550123');
    }

    public function testAHugeInputIsRefusedAtOnce(): void
    {
        $started = hrtime(true);

        try {
            PhoneNumber::parse('1'.str_repeat(' ', 1_000_000).'y');
            self::fail('A huge input is no phone number.');
        } catch (InvalidPhoneNumber) {
            self::assertLessThan(50_000_000, hrtime(true) - $started);
        }
    }

    public function testALongInputIsCutInCharacters(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 100).'" is not a phone number.');

        PhoneNumber::parse(str_repeat('é', 150));
    }

    /**
     * @return iterable<string, array{string, ?string, PhoneNumberType}>
     */
    public static function types(): iterable
    {
        yield 'North America' => ['+1 514 555 0123', 'CA', PhoneNumberType::FixedLineOrMobile];
        yield 'toll free' => ['+1 800 555 0199', 'US', PhoneNumberType::TollFree];
        yield 'mobile' => ['+33 6 12 34 56 78', 'FR', PhoneNumberType::Mobile];
        yield 'fixed line' => ['+33 1 23 45 67 89', 'FR', PhoneNumberType::FixedLine];
        yield 'premium rate' => ['+33 8 91 12 34 56', 'FR', PhoneNumberType::PremiumRate];
        yield 'shared cost' => ['+33 8 84 01 23 45', 'FR', PhoneNumberType::SharedCost];
        yield 'voip' => ['+33 9 12 34 56 78', 'FR', PhoneNumberType::Voip];
        yield 'uan' => ['+33 8 06 12 34 56', 'FR', PhoneNumberType::Uan];
        yield 'personal number' => ['+44 70 1234 5678', 'GB', PhoneNumberType::PersonalNumber];
        yield 'pager' => ['+44 7646 412345', 'GB', PhoneNumberType::Pager];
        yield 'voicemail' => ['+41 860 123 456 789', 'CH', PhoneNumberType::Voicemail];
        yield 'a region told by its leading digits before its plan' => ['+44 7624 123456', 'IM', PhoneNumberType::Mobile];
        yield 'one region sharing a country code' => ['+1 876 555 1234', 'JM', PhoneNumberType::Mobile];
        yield 'a region told by its leading digits' => ['+39 06 698 12345', 'VA', PhoneNumberType::FixedLine];
        yield 'no country' => ['+800 1234 5678', '001', PhoneNumberType::TollFree];
        yield 'not in the plan' => ['+33 0 12 34 56 78', null, PhoneNumberType::Unknown];
        yield 'too short for its plan' => ['+33 6 12 34 56', null, PhoneNumberType::Unknown];
        yield 'too short in a shared country code' => ['+1 514 555', null, PhoneNumberType::Unknown];
    }

    #[DataProvider('types')]
    public function testANumberHasItsRegionAndType(string $input, ?string $region, PhoneNumberType $type): void
    {
        $number = PhoneNumber::parse($input);

        self::assertSame($region, $number->region());
        self::assertSame($type, $number->type());
        self::assertSame(PhoneNumberType::Unknown !== $type, $number->isValid());
    }

    /**
     * @return iterable<string, array{string, ?string, string, string, string}>
     */
    public static function formats(): iterable
    {
        yield 'North America' => ['(514) 555-0123', 'CA', '+1 514-555-0123', '(514) 555-0123', 'tel:+1-514-555-0123'];
        yield 'France' => ['0612345678', 'FR', '+33 6 12 34 56 78', '06 12 34 56 78', 'tel:+33-6-12-34-56-78'];
        yield 'a rule on another group' => ['011 15 2345-6789', 'AR', '+54 9 11 2345-6789', '011 15-2345-6789', 'tel:+54-9-11-2345-6789'];
        yield 'a rule in parentheses' => ['8 800 555 35 35', 'RU', '+7 800 555-35-35', '8 (800) 555-35-35', 'tel:+7-800-555-35-35'];
        yield 'formatted by the main country' => ['+1 876 555 1234', null, '+1 876-555-1234', '(876) 555-1234', 'tel:+1-876-555-1234'];
        yield 'no country' => ['+800 1234 5678', null, '+800 1234 5678', '1234 5678', 'tel:+800-1234-5678'];
        yield 'no format matching' => ['+33 12', null, '+33 12', '12', 'tel:+33-12'];
        yield 'a national format not used internationally' => ['3010', 'FR', '+33 3010', '3010', 'tel:+33-3010'];
        yield 'the first format whose digits match' => ['+33 1 23 45 67 89', null, '+33 1 23 45 67 89', '01 23 45 67 89', 'tel:+33-1-23-45-67-89'];
        yield 'a format without national prefix' => ['+39 06 6982 1234', null, '+39 06 6982 1234', '06 6982 1234', 'tel:+39-06-6982-1234'];
    }

    #[DataProvider('formats')]
    public function testANumberIsWrittenInEachFormat(string $input, ?string $region, string $international, string $national, string $link): void
    {
        $number = PhoneNumber::parse($input, $region);

        self::assertSame($international, $number->format(PhoneFormat::International));
        self::assertSame($national, $number->format(PhoneFormat::National));
        self::assertSame($link, $number->format(PhoneFormat::Rfc3966));
    }

    public function testAnExtensionIsWrittenAsItsCountryWritesIt(): void
    {
        $canada = PhoneNumber::parse('+1 514 555 0123 x12');
        $britain = PhoneNumber::parse('+44 20 7946 0958 ext. 12');

        self::assertSame(['+15145550123', '+1 514-555-0123 ext. 12', '(514) 555-0123 ext. 12', 'tel:+1-514-555-0123;ext=12', '+15145550123;ext=12'], [$canada->format(PhoneFormat::E164), $canada->format(PhoneFormat::International), $canada->format(PhoneFormat::National), $canada->format(PhoneFormat::Rfc3966), (string) $canada]);
        self::assertSame(['+44 20 7946 0958 x12', '020 7946 0958 x12'], [$britain->format(PhoneFormat::International), $britain->format(PhoneFormat::National)]);
    }

    public function testANumberIsReadBackFromItsString(): void
    {
        foreach (['+15145550123', '+15145550123;ext=12', '+390669821234', '+78005553535', '+5491123456789'] as $stored) {
            self::assertSame($stored, (string) PhoneNumber::parse($stored));
        }
    }

    public function testNumbersCompare(): void
    {
        $number = PhoneNumber::parse('+1 514 555 0123');

        self::assertTrue($number->equals(PhoneNumber::parse('(514) 555-0123', 'CA')));
        self::assertFalse($number->equals(PhoneNumber::parse('+1 514 555 0123 x1')));
        self::assertFalse($number->equals(PhoneNumber::parse('+1 514 555 0124')));
        self::assertFalse(PhoneNumber::parse('+7 800 555 35 35')->equals(PhoneNumber::parse('+1 800 555 3535')));
        self::assertSame([1, '5145550123', null], [$number->countryCode, $number->nationalNumber, $number->extension]);
    }
}
