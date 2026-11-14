<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\Locale\BuiltInLocales;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalePack::class)]
#[CoversClass(LocalePacks::class)]
#[CoversClass(BuiltInLocales::class)]
final class LocalePacksTest extends TestCase
{
    public function testTheBuiltInPacks(): void
    {
        self::assertSame(['fr_CA', 'en_CA', 'fr_FR', 'en_US', 'en_GB', 'en_AU', 'de_DE', 'es_ES'], LocalePacks::builtIn()->codes());
        self::assertSame('Québec', LocalePacks::builtIn()->get('fr_CA')->regions[0]);
    }

    /**
     * The data of the packs, fingerprinted: a change to a name, a street or a range is a deliberate one, made here too.
     */
    public function testTheDataOfThePacksIsThePublishedOne(): void
    {
        $data = array_map(get_object_vars(...), BuiltInLocales::all());

        self::assertSame('bafd2c1a5079bfd65a0f5a76a49fae02', hash('xxh128', json_encode($data, \JSON_THROW_ON_ERROR)));
    }

    /**
     * Every telephone pattern of a pack is in its country's fictional range.
     */
    public function testTelephonePatternsAreFictionalRanges(): void
    {
        $ranges = [
            'fr_CA' => '/^\d{3} 555-01##$/',
            'en_CA' => '/^\d{3} 555-01##$/',
            'en_US' => '/^\d{3} 555-01##$/',
            'fr_FR' => '/^0(1 99 00|2 61 91|3 53 01|4 65 71|5 36 49|6 39 98) ## ##$/',
            'en_GB' => '/^(07700 900###|020 7946 0###|0113 496 0###|0161 496 0###)$/',
            'en_AU' => '/^0[2378] (5550|7010) ####$/',
        ];

        foreach (BuiltInLocales::all() as $pack) {
            foreach ($pack->phones as $phone) {
                self::assertMatchesRegularExpression($ranges[$pack->code], $phone, $pack->code);
            }
        }
    }

    public function testAnApplicationPackIsAddedOrReplacesABuiltInOne(): void
    {
        $quebec = LocalePacks::builtIn()->get('fr_CA');
        $belgium = new LocalePack('fr_BE', ['Lucie'], ['Peeters'], ['de la Loi'], ['{number}, rue {street}'], ['Liège'], ['Wallonie'], ['####'], [], 'BE', 'Belgique', '{line}, {postal_code} {city}');
        $packs = LocalePacks::builtIn()->with([$belgium, new LocalePack('fr_CA', ['Ada'], ['Roy'], ['Principale'], ['{number}, rue {street}'], ['Laval'], ['Québec'], ['H#? #?#'], [], 'CA', 'Canada', '{line}')]);

        self::assertSame($belgium, $packs->get('fr_BE'));
        self::assertSame(['Ada'], $packs->get('fr_CA')->givenNames);
        self::assertSame($quebec->givenNames, LocalePacks::builtIn()->get('fr_CA')->givenNames, 'The registry is immutable.');
        self::assertSame(['Peeters'], $belgium->familyNames);
        self::assertSame(['de la Loi'], $belgium->streets);
        self::assertSame(['{number}, rue {street}'], $belgium->streetFormats);
        self::assertSame(['Liège'], $belgium->cities);
        self::assertSame(['####'], $belgium->postalCodes);
        self::assertSame(['BE', 'Belgique', '{line}, {postal_code} {city}'], [$belgium->country, $belgium->countryName, $belgium->addressFormat]);
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('No locale pack answers to "it_IT"; the packs are fr_CA, en_CA, fr_FR, en_US, en_GB, en_AU, de_DE, es_ES.');

        LocalePacks::builtIn()->get('it_IT');
    }

    public function testAMalformedCodeIsRefused(): void
    {
        foreach (['fr', 'fr-CA', 'FR_CA', 'fr_CA ', 'fran_CA'] as $code) {
            try {
                new LocalePack($code, ['A'], ['B'], ['C'], ['D'], ['E'], ['F'], ['G'], [], 'CA', 'Canada', '{line}');
                self::fail($code);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame(\sprintf('"%s" is not a locale code such as "fr_CA".', $code), $invalidPrivacyValue->getMessage());
            }
        }

        self::assertSame('ast_ES', new LocalePack('ast_ES', ['A'], ['B'], ['C'], ['D'], ['E'], ['F'], ['G'], [], 'ES', 'España', '{line}')->code);
    }

    public function testEveryListButTheTelephonesIsRequired(): void
    {
        $lists = ['given names', 'family names', 'streets', 'street formats', 'cities', 'regions', 'postal codes'];

        foreach ($lists as $index => $list) {
            $arguments = array_fill(0, 7, ['x']);
            $arguments[$index] = [];

            try {
                new LocalePack('fr_CA', ...[...$arguments, [], 'CA', 'Canada', '{line}']);
                self::fail($list);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertSame(\sprintf('The locale pack "fr_CA" has no %s.', $list), $invalidPrivacyValue->getMessage());
            }
        }
    }
}
