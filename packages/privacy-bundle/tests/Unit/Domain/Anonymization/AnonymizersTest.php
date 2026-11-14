<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\AddressPart;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\AddressAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\ConstantAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\DateShiftAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\EmailAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\HashAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\IbanAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\LoremAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\NullAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PatternAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PersonNameAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PhoneAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack;
use Phil\PrivacyBundle\Domain\Anonymization\NamePart;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Anonymizers::class)]
#[CoversClass(AnonymizationContext::class)]
#[CoversClass(EmailAnonymizer::class)]
#[CoversClass(PersonNameAnonymizer::class)]
#[CoversClass(PhoneAnonymizer::class)]
#[CoversClass(AddressAnonymizer::class)]
#[CoversClass(IbanAnonymizer::class)]
#[CoversClass(DateShiftAnonymizer::class)]
#[CoversClass(ConstantAnonymizer::class)]
#[CoversClass(NullAnonymizer::class)]
#[CoversClass(HashAnonymizer::class)]
#[CoversClass(LoremAnonymizer::class)]
#[CoversClass(PatternAnonymizer::class)]
#[CoversClass(NamePart::class)]
#[CoversClass(AddressPart::class)]
final class AnonymizersTest extends TestCase
{
    public function testTheBuiltInTypes(): void
    {
        self::assertSame(['email', 'firstname', 'lastname', 'name', 'phone', 'address', 'iban', 'date_shift', 'constant', 'null', 'hash', 'lorem', 'pattern'], Anonymizers::builtIn()->types());
    }

    /**
     * Each built-in type, on a seeded source: the values below come out on every run.
     */
    public function testEachTypeOnASeededSource(): void
    {
        $anonymizers = Anonymizers::builtIn();

        self::assertSame('user.b930ea84bcaea4db@example.com', $anonymizers->get('email')->anonymize('ada@example.com', Contexts::of()));
        self::assertSame('Maélie', $anonymizers->get('firstname')->anonymize('Ada', Contexts::of()));
        self::assertSame('Lévesque', $anonymizers->get('lastname')->anonymize('Lovelace', Contexts::of()));
        self::assertSame('Maélie Gauthier', $anonymizers->get('name')->anonymize('Ada Lovelace', Contexts::of()));
        self::assertSame('819 555-0176', $anonymizers->get('phone')->anonymize('514 000-0000', Contexts::of()));
        self::assertSame('8474, boulevard du Moulin', $anonymizers->get('address')->anonymize('1 rue Ada', Contexts::of()));
        self::assertSame('FR7757619051788780246367647', $anonymizers->get('iban')->anonymize('x', Contexts::of(['country' => 'FR'])));
        self::assertSame('2024-03-20', $anonymizers->get('date_shift')->anonymize('2024-03-15', Contexts::of()));
        self::assertSame(5, $anonymizers->get('constant')->anonymize('x', Contexts::of(['value' => 5])));
        self::assertNull($anonymizers->get('null')->anonymize('x', Contexts::of()));
        self::assertSame('555eb18440ae9c41f5d150d5628fa2b28bbdd8c479600188ebc68db666b0195f', $anonymizers->get('hash')->anonymize('x', Contexts::of()));
        self::assertSame('Duis mollit nulla.', $anonymizers->get('lorem')->anonymize('x', Contexts::of(['words' => 3])));
        self::assertSame('M-57-KB', $anonymizers->get('pattern')->anonymize('x', Contexts::of(['pattern' => 'M-##-??'])));
    }

    public function testAnApplicationAnonymizerIsAddedOrReplacesABuiltInOne(): void
    {
        $upper = new class implements Anonymizer {
            public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
            {
                return 'X';
            }
        };
        $built = Anonymizers::builtIn();
        $anonymizers = $built->with(['vat_number' => $upper, 'email' => $upper]);

        self::assertSame($upper, $anonymizers->get('vat_number'));
        self::assertSame($upper, $anonymizers->get('email'));
        self::assertInstanceOf(EmailAnonymizer::class, $built->get('email'), 'The registry is immutable.');
        self::assertSame([...$built->types(), 'vat_number'], $anonymizers->types());
    }

    public function testAMalformedOrUnknownTypeIsRefused(): void
    {
        try {
            Anonymizers::builtIn()->with(['Vat' => new NullAnonymizer()]);
            self::fail('A malformed type is refused.');
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertSame('"Vat" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $invalidPrivacyValue->getMessage());
        }

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('No anonymizer answers to the type "vat"; the types are email, firstname, lastname, name, phone, address, iban, date_shift, constant, null, hash, lorem, pattern.');
        Anonymizers::builtIn()->get('vat');
    }

    /**
     * Equal addresses give equal results within a run, whatever their case; another salt gives another result.
     */
    public function testAnEmailIsDerivedUnderTheSalt(): void
    {
        $email = new EmailAnonymizer();

        self::assertSame($email->anonymize('ADA@example.com', Contexts::of(seed: 2)), $email->anonymize('ada@example.com', Contexts::of()));
        self::assertSame($email->anonymize('ÉLODIE@example.com', Contexts::of()), $email->anonymize('élodie@example.com', Contexts::of()));
        self::assertNotSame($email->anonymize('ada@example.com', Contexts::of(salt: 'other')), $email->anonymize('ada@example.com', Contexts::of()));
        self::assertNotSame($email->anonymize('bob@example.com', Contexts::of()), $email->anonymize('ada@example.com', Contexts::of()));
        self::assertSame('member.b930ea84bcaea4db@example.org', $email->anonymize('ada@example.com', Contexts::of(['domain' => 'example.org', 'prefix' => 'member'])));
    }

    public function testAPhoneFollowsAPatternOrRefusesAPackWithoutFictionalRange(): void
    {
        $phone = new PhoneAnonymizer();

        self::assertSame('+1 #KI', $phone->anonymize('x', Contexts::of(['pattern' => '+1 \#?*'])));
        self::assertMatchesRegularExpression('/^\+49 \d{3}$/', $phone->anonymize('x', Contexts::of(['pattern' => '+49 ###'], 'de_DE')));
        self::assertMatchesRegularExpression('/^0(1 99 00|2 61 91|3 53 01|4 65 71|5 36 49|6 39 98) \d\d \d\d$/', $phone->anonymize('x', Contexts::of([], 'fr_FR')));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('The locale pack "de_DE" has no fictional telephone range: give the phone anonymizer a "pattern" option.');
        $phone->anonymize('x', Contexts::of([], 'de_DE'));
    }

    public function testEveryPartOfAnAddress(): void
    {
        $address = new AddressAnonymizer();

        self::assertSame('8474, boulevard du Moulin, Rimouski (Québec) J5R 7Y8', $address->anonymize('x', Contexts::of(['part' => 'full'])));
        self::assertSame('G7K 1Z0', $address->anonymize('x', Contexts::of(['part' => 'postal_code'])));
        self::assertSame('Québec', $address->anonymize('x', Contexts::of(['part' => 'region'])));
        self::assertSame('Canada', $address->anonymize('x', Contexts::of(['part' => 'country'])));
        $belgium = new LocalePack('fr_BE', ['Lucie'], ['Peeters'], ['de la Loi'], ['{number}, rue {street}'], ['Liège'], ['Wallonie'], ['4000'], [], 'BE', 'Belgique', '{line}, {postal_code} {city} ({region}), {country}');
        self::assertSame('8474, rue de la Loi, 4000 Liège (Wallonie), Belgique', $address->anonymize('x', new AnonymizationContext(new AnonymizerOptions(['part' => 'full']), $belgium, Contexts::of()->random, 'salt')));
        self::assertContains($address->anonymize('x', Contexts::of(['part' => 'city'])), Contexts::of()->locale->cities);
        self::assertMatchesRegularExpression('/^\d{1,4}, \w+ [^,]+, \d{5} \p{Lu}/u', $address->anonymize('x', Contexts::of(['part' => 'full'], 'fr_FR')));
        self::assertMatchesRegularExpression('/^\d{1,4} \w+ (Street|Avenue|Boulevard|Lane), \w+, [A-Z]{2} \d{5}$/', $address->anonymize('x', Contexts::of(['part' => 'full'], 'en_US')));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"street" is not an address part; the parts are line, city, region, postal_code, country and full.');
        $address->anonymize('x', Contexts::of(['part' => 'street']));
    }

    /**
     * Every account shape gives an IBAN whose check digits a validator accepts; the country is the pack's by default.
     */
    public function testAnIbanHasRightCheckDigits(): void
    {
        $iban = new IbanAnonymizer();

        foreach (['AT', 'BE', 'CH', 'DE', 'ES', 'FR', 'GB', 'IE', 'IT', 'LU', 'NL', 'PT'] as $index => $country) {
            for ($seed = 1; $seed <= 5; ++$seed) {
                $number = $iban->anonymize('x', Contexts::of(['country' => $country], seed: $seed * 10 + $index));
                self::assertStringStartsWith($country, $number);
                self::assertSame(1, $this->modulo97(substr($number, 4).substr($number, 0, 4)), $number);
            }
        }

        self::assertSame('GB21KXKB90517887802463', $iban->anonymize('x', Contexts::of(['country' => 'GB'])));
        self::assertSame(27, \strlen($iban->anonymize('x', Contexts::of([], 'fr_FR'))));
        self::assertStringStartsWith('ES', $iban->anonymize('x', Contexts::of([], 'es_ES')));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('The iban anonymizer has no account shape for "CA"; give it a "country" option among AT, BE, CH, DE, ES, FR, GB, IE, IT, LU, NL, PT.');
        $iban->anonymize('x', Contexts::of());
    }

    public function testADateMovesWithinItsRangeAndKeepsItsFormat(): void
    {
        $shift = new DateShiftAnonymizer();
        $moved = [];

        for ($seed = 1; $seed <= 40; ++$seed) {
            $date = $shift->anonymize('2024-03-15 10:20:30', Contexts::of(['days' => 3], seed: $seed));
            self::assertMatchesRegularExpression('/^2024-03-1[2-8] 10:20:30$/', $date);
            self::assertNotSame('2024-03-15 10:20:30', $date, 'The shift is never zero.');
            $moved[$date] = true;
        }

        self::assertCount(6, $moved, 'Every shift from three days back to three days forward comes out.');
        self::assertSame('2024-03-17 10:20:30', $shift->anonymize('2024-03-15 10:20:30', Contexts::of(['days' => 3])));
        self::assertSame('2024-03-20T00:00:00+02:00', $shift->anonymize('2024-03-15T00:00:00+02:00', Contexts::of()));
        self::assertSame('2024-03-20 00:00:00.250000', $shift->anonymize('2024-03-15 00:00:00.250000', Contexts::of()));
        self::assertSame('20/03/2024', $shift->anonymize('15/03/2024', Contexts::of(['format' => 'd/m/Y'])));
        self::assertSame('20240320', $shift->anonymize(20240315, Contexts::of(['format' => 'Ymd'])));
        self::assertSame(
            ['2024-03-17', '2024-03-16', '2024-03-13', '2024-03-18', '2024-03-12', '2024-03-16', '2024-03-14', '2024-03-16'],
            array_map(static fn (int $seed): string => $shift->anonymize('2024-03-15', Contexts::of(['days' => 3], seed: $seed)), range(1, 8)),
        );
    }

    public function testADateThatIsNoneOfTheFormatsIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"2024-02-30" is not a date the date_shift anonymizer reads; give it a "format" option.');

        new DateShiftAnonymizer()->anonymize('2024-02-30', Contexts::of());
    }

    public function testAHashIsAnHmacUnderTheSalt(): void
    {
        $hash = new HashAnonymizer();

        self::assertSame(hash_hmac('sha256', '42', 'salt'), $hash->anonymize(42, Contexts::of()));
        self::assertSame('001d778d', $hash->anonymize('x', Contexts::of(['algorithm' => 'md5', 'length' => 8])));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('"crc" is not a hash algorithm this PHP offers for HMAC.');
        $hash->anonymize('x', Contexts::of(['algorithm' => 'crc']));
    }

    public function testLoremHasTheWordsAsked(): void
    {
        $text = new LoremAnonymizer()->anonymize('x', Contexts::of());

        self::assertCount(12, explode(' ', $text));
        self::assertMatchesRegularExpression('/^[A-Z][a-z]+( [a-z]+)*\.$/', $text);
    }

    public function testAConstantKeepsItsTypeAndDefaultsToTheEmptyString(): void
    {
        self::assertSame('', new ConstantAnonymizer()->anonymize('x', Contexts::of()));
        self::assertTrue(new ConstantAnonymizer()->anonymize('x', Contexts::of(['value' => true])));
        self::assertNull(new ConstantAnonymizer()->anonymize('x', Contexts::of(['value' => null])));
    }

    public function testAPatternIsRequired(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('The anonymizer option "pattern" is required.');

        new PatternAnonymizer()->anonymize('x', Contexts::of());
    }

    public function testEachNamePartDrawsFromItsList(): void
    {
        $context = Contexts::of([], 'en_GB');

        self::assertContains(new PersonNameAnonymizer(NamePart::Given)->anonymize('x', $context), $context->locale->givenNames);
        self::assertContains(new PersonNameAnonymizer(NamePart::Family)->anonymize('x', $context), $context->locale->familyNames);
        [$given, $family] = explode(' ', new PersonNameAnonymizer(NamePart::Full)->anonymize('x', $context));
        self::assertContains($given, $context->locale->givenNames);
        self::assertContains($family, $context->locale->familyNames);
    }

    /**
     * The pattern placeholders draw from their whole alphabet, and an escape writes the next character as it is.
     */
    public function testAPatternDrawsFromEachAlphabet(): void
    {
        $seen = ['#' => [], '?' => [], '*' => []];

        for ($seed = 1; $seed <= 60; ++$seed) {
            [$digit, $letter, $either] = str_split(Contexts::of(seed: $seed)->fill('#?*'));
            $seen['#'][$digit] = true;
            $seen['?'][$letter] = true;
            $seen['*'][$either] = true;
        }

        self::assertSame(str_split('0123456789'), $this->sorted($seen['#']));
        self::assertGreaterThan(20, \count($seen['?']));
        self::assertSame([], array_diff(array_keys($seen['?']), range('A', 'Z')));
        self::assertGreaterThan(\count($seen['?']), \count($seen['*']) + 5);
        self::assertSame('é\#?*\\x', Contexts::of()->fill('é\\\\\#\?\*\\\\x'));
    }

    /**
     * @param array<array-key, true> $set
     *
     * @return list<string>
     */
    private function sorted(array $set): array
    {
        $keys = array_map(strval(...), array_keys($set));
        sort($keys);

        return $keys;
    }

    private function modulo97(string $characters): int
    {
        $digits = '';

        foreach (str_split($characters) as $character) {
            $digits .= $character >= 'A' ? (string) (\ord($character[0]) - 55) : $character;
        }

        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + \ord($digit[0]) - 48) % 97;
        }

        return $remainder;
    }
}
