<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Unit;

use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\Address\AddressFormat;
use Phil\Address\AddressFormats;
use Phil\Address\AddressFormatter;
use Phil\Address\AddressValidator;
use Phil\Address\AddressViolation;
use Phil\Address\Subdivision;
use Phil\Address\Subdivisions;
use Phil\Address\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressFormat::class)]
#[CoversClass(AddressFormats::class)]
#[CoversClass(Subdivision::class)]
#[CoversClass(Subdivisions::class)]
#[CoversClass(AddressValidator::class)]
#[CoversClass(AddressViolation::class)]
#[CoversClass(Violation::class)]
#[CoversClass(AddressFormatter::class)]
final class AddressRulesTest extends TestCase
{
    public function testACountryHasItsFormat(): void
    {
        $canada = AddressFormats::of('ca');

        self::assertSame(['CA', 'province', 'city', 'postal', 1, null, null], [$canada->country, $canada->administrativeAreaType, $canada->localityType, $canada->postalCodeType, $canada->subdivisionDepth, $canada->localFormat, $canada->locale]);
        self::assertSame([AddressField::GivenName, AddressField::FamilyName, AddressField::Organization, AddressField::AddressLine1, AddressField::AddressLine2, AddressField::AddressLine3, AddressField::Locality, AddressField::AdministrativeArea, AddressField::PostalCode], $canada->fields());
        self::assertSame([true, false, true, false], [$canada->uses(AddressField::PostalCode), $canada->uses(AddressField::SortingCode), $canada->requires(AddressField::PostalCode), $canada->requires(AddressField::AddressLine2)]);
        self::assertSame([AddressField::AdministrativeArea], $canada->subdivisionFields());
        self::assertSame([AddressField::AdministrativeArea, AddressField::Locality, AddressField::DependentLocality], AddressFormats::of('CN')->subdivisionFields());
        self::assertSame(AddressFormats::of('CA'), $canada);

        $japan = AddressFormats::of('JP');
        self::assertSame(['ja', 'prefecture', 'zip'], [$japan->locale, $japan->administrativeAreaType, AddressFormats::of('US')->postalCodeType]);
        self::assertStringStartsWith('〒', (string) $japan->localFormat);
        self::assertSame(['locality' => 'Singapore'], AddressFormats::of('SG')->defaults);
        self::assertSame('SE-', AddressFormats::of('SE')->postalCodePrefix);
        self::assertSame([AddressField::AddressLine1], AddressFormats::of('AG')->required);
        self::assertContains(AddressField::Locality, AddressFormats::of('AG')->uppercase);

        $repeated = new AddressFormat('ZZ', "%locality %postalCode\n%locality %addressLine1", null, null, [], [], null, null, 'province', 'city', 'suburb', 'postal', 0, []);
        self::assertSame([AddressField::Locality, AddressField::PostalCode, AddressField::AddressLine1], $repeated->fields());

        $antarctica = AddressFormats::of('AQ');
        self::assertSame(['AQ', [AddressField::AddressLine1, AddressField::Locality], 0, []], [$antarctica->country, $antarctica->required, $antarctica->subdivisionDepth, $antarctica->subdivisionFields()]);
    }

    public function testACountryHasItsSubdivisions(): void
    {
        $quebec = Subdivisions::find('Québec', 'CA');

        self::assertNotNull($quebec);
        self::assertSame(['QC', 'QC', 'QC', 'Quebec', 'Québec', 'fr', false], [$quebec->id, $quebec->code, $quebec->localCode, $quebec->name, $quebec->localName, $quebec->locale, $quebec->hasChildren]);
        self::assertSame(['Québec', 'Québec', 'Quebec', 'Quebec'], [$quebec->nameIn('fr'), $quebec->nameIn('fr_CA'), $quebec->nameIn('en'), $quebec->nameIn(null)]);
        self::assertSame([true, true, true, false], [$quebec->is(' qc '), $quebec->is('QUEBEC'), $quebec->is('québec'), $quebec->is('Ontario')]);
        self::assertCount(13, Subdivisions::of('CA'));
        self::assertSame(Subdivisions::of('CA'), Subdivisions::of('ca'));
        self::assertSame('Rio Branco', Subdivisions::find('rio branco', 'BR', 'AC')?->name);
        self::assertTrue(Subdivisions::find('AC', 'BR')?->hasChildren);
        self::assertSame([], Subdivisions::of('DE', 'Berlin'));
        self::assertSame([], Subdivisions::of('CA', 'a/b'));
        self::assertNotSame([], Subdivisions::of('TW', 'CHA'));
        self::assertSame([true, true, true, true], [$quebec->is('QUÉBEC'), Subdivisions::find('île-du-prince-édouard', 'CA')?->is('PE'), Subdivisions::find('13', 'JP')?->is('Tokyo'), 'Québec' === $quebec->nameIn('FR-ca')]);
        self::assertSame('Rio Branco', Subdivisions::find('Rio Branco', 'BR', ...['state' => 'AC'])?->name);
        self::assertSame('Gulou Qu', Subdivisions::find('鼓楼区', 'CN', 'FJ', 'Fuzhou Shi')?->name);
        self::assertSame([], Subdivisions::of('CN', 'FJ', 'Atlantis'));
        self::assertNull(Subdivisions::find('Atlantis', 'CA'));
        self::assertSame('Tokyo', Subdivisions::find('東京都', 'JP')?->name);
        self::assertSame('東京都', Subdivisions::find('Tokyo', 'JP')?->nameIn('ja'));
        self::assertSame('Mountain View', new Subdivision('x', 'x', null, 'Mountain View', 'Montagne', null, null, false, null)->nameIn('fr'));
        self::assertSame('Mountain View', new Subdivision('x', 'x', null, 'Mountain View', null, null, null, false, 'fr')->nameIn('fr'));
        self::assertTrue(new Subdivision('QC', 'QC', null, 'Quebec', null, 'CA-QC', null, false, null)->is('ca-qc'));
    }

    /**
     * A subdivision is found by its identifier, its codes and its names, in lowercase, whatever the list's size.
     */
    public function testASubdivisionIsFoundByItsKeys(): void
    {
        self::assertSame(['qc', 'qc', 'qc', 'quebec', 'québec'], Subdivisions::find('QC', 'CA')?->keys());
        self::assertSame(['x', 'ÿ', 'mountain view', 'ca-x'], new Subdivision('X', 'Ÿ', null, 'Mountain View', null, 'CA-X', null, false, null)->keys());
        self::assertSame('QC', Subdivisions::find(" Qu\u{E9}BEC\t", 'CA')?->id);
        self::assertSame('QC', Subdivisions::find('QUÉBEC', 'CA')?->id);
        self::assertSame(Subdivisions::of('BR', 'AC'), Subdivisions::of('BR', ...['state' => 'AC']));
        self::assertNotSame([], Subdivisions::of('BR', ...['state' => 'AC']));
        self::assertSame('QC', Subdivisions::find('qc', 'ca')?->id);
        self::assertSame('ON', Subdivisions::find('Ontario', 'CA')?->id);
        self::assertNull(Subdivisions::find('', 'CA'));
        self::assertNull(Subdivisions::find('Quebec City', 'CA'));
        self::assertNull(Subdivisions::find('QC', 'DE', 'Berlin'));

        $minasGerais = Subdivisions::of('BR', 'MG');
        $last = $minasGerais[\count($minasGerais) - 1];
        self::assertGreaterThan(800, \count($minasGerais));
        self::assertSame($last, Subdivisions::find(mb_strtoupper($last->name), 'BR', 'MG'));
        self::assertSame($minasGerais[0], Subdivisions::find($minasGerais[0]->id, 'BR', 'MG'));
    }

    /**
     * @return iterable<string, array{Address, list<array{string, string}>}>
     */
    public static function addresses(): iterable
    {
        yield 'a complete address' => [Address::of('CA', ['addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'Québec', 'postalCode' => 'h2x 3k8']), []];
        yield 'missing fields' => [Address::of('CA', ['addressLine2' => 'Suite 4']), [['addressLine1', 'missing'], ['locality', 'missing'], ['administrativeArea', 'missing'], ['postalCode', 'missing']]];
        yield 'an unused field' => [Address::of('DE', ['addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117', 'administrativeArea' => 'Berlin']), [['administrativeArea', 'unused']]];
        yield 'a wrong postal code' => [Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8 0']), [['postalCode', 'invalid_postal_code']]];
        yield 'a postal code before its pattern' => [Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => '0H2X 3K8']), [['postalCode', 'invalid_postal_code']]];
        yield 'an unknown subdivision' => [Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'Atlantis', 'postalCode' => 'H2X 3K8']), [['administrativeArea', 'unknown_subdivision']]];
        yield 'a country without postal codes' => [Address::of('AG', ['addressLine1' => '1 High St', 'postalCode' => 'X1']), [['postalCode', 'unused']]];
        yield 'a country without subdivisions' => [Address::of('FR', ['addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001']), []];
        yield 'a city below its state' => [Address::of('BR', ['addressLine1' => 'Rua X, 10', 'locality' => 'Rio Branco', 'administrativeArea' => 'AC', 'postalCode' => '69900-000']), []];
        yield 'a city not below its state' => [Address::of('BR', ['addressLine1' => 'Rua X, 10', 'locality' => 'Atlantis', 'administrativeArea' => 'AC', 'postalCode' => '69900-000']), [['locality', 'unknown_subdivision']]];
        yield 'a district below its city' => [Address::of('CN', ['addressLine1' => '1 Road', 'dependentLocality' => '鼓楼区', 'locality' => '福州市', 'administrativeArea' => '福建省', 'postalCode' => '350001']), []];
        yield 'a district not below its city' => [Address::of('CN', ['addressLine1' => '1 Road', 'dependentLocality' => 'Atlantis', 'locality' => '福州市', 'administrativeArea' => '福建省', 'postalCode' => '350001']), [['dependentLocality', 'unknown_subdivision']]];
        yield 'a postal code of another department' => [Address::of('CO', ['addressLine1' => 'Calle 1', 'locality' => 'Bogotá', 'administrativeArea' => 'DC', 'postalCode' => '050001']), [['postalCode', 'invalid_postal_code']]];
        yield 'a postal code of its department' => [Address::of('CO', ['addressLine1' => 'Calle 1', 'locality' => 'Bogotá', 'administrativeArea' => 'DC', 'postalCode' => '110111']), []];
        yield 'a subdivision without children' => [Address::of('US', ['addressLine1' => '1 Main St', 'locality' => 'Austin', 'administrativeArea' => 'TX', 'postalCode' => '78701']), []];
        yield 'no subdivision given' => [Address::of('CN', ['addressLine1' => '1 Road', 'locality' => 'Atlantis', 'postalCode' => '350001']), [['administrativeArea', 'missing']]];
    }

    /**
     * @param list<array{string, string}> $violations
     */
    #[DataProvider('addresses')]
    public function testAnAddressIsCheckedAgainstItsCountry(Address $address, array $violations): void
    {
        self::assertSame($violations, array_map(static fn (AddressViolation $violation): array => [$violation->field->value, $violation->violation->value], AddressValidator::violations($address)));
        self::assertSame([] === $violations, AddressValidator::isValid($address));
    }

    /**
     * @return iterable<string, array{Address, ?string, string}>
     */
    public static function written(): iterable
    {
        $montreal = Address::of('CA', ['givenName' => 'Ada', 'familyName' => 'Lovelace', 'addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'Québec', 'postalCode' => 'H2X 3K8']);
        $tokyo = Address::of('JP', ['familyName' => '山田', 'givenName' => '太郎', 'addressLine1' => '千代田1-1', 'locality' => '千代田区', 'administrativeArea' => 'Tokyo', 'postalCode' => '100-0001']);

        yield 'Canada' => [$montreal, 'en', "Ada Lovelace\n1234 rue Saint-Denis\nMontréal QC H2X 3K8"];
        yield 'a separator left over' => [Address::of('US', ['addressLine1' => '1 Main St', 'administrativeArea' => 'TX', 'postalCode' => '78701']), null, "1 Main St\nTX 78701"];
        yield 'a line of punctuation' => [Address::of('US', ['addressLine1' => '1 Main St', 'locality' => '()']), null, '1 Main St'];
        yield 'Japan in English' => [$tokyo, 'en', "山田 太郎\n千代田1-1\n千代田区, Tokyo\n100-0001"];
        yield 'Japan in Japanese' => [$tokyo, 'ja_JP', "〒100-0001\n東京都千代田区\n千代田1-1\n山田 太郎"];
        yield 'Japan without language' => [$tokyo, null, "山田 太郎\n千代田1-1\n千代田区, Tokyo\n100-0001"];
        yield 'a subdivision by its local code' => [Address::of('CA', ['addressLine1' => '1', 'locality' => 'Québec', 'administrativeArea' => 'Quebec', 'postalCode' => 'G1A 0A1']), 'fr', "1\nQuébec QC G1A 0A1"];
        yield 'a subdivision written by name' => [Address::of('CN', ['addressLine1' => '1 Road', 'locality' => 'Fuzhou Shi', 'administrativeArea' => 'Fujian Sheng', 'postalCode' => '350001']), 'en', "1 Road\nFuzhou Shi\nFujian Sheng, 350001"];
        yield 'an unknown subdivision as given' => [Address::of('CA', ['addressLine1' => '1', 'locality' => 'Montréal', 'administrativeArea' => 'Atlantis']), null, "1\nMontréal Atlantis"];
        yield 'a subdivision with a code of its own' => [Address::of('UA', ['addressLine1' => 'Khreshchatyk 1', 'locality' => 'Kyiv', 'administrativeArea' => 'Kyiv', 'postalCode' => '01001']), 'en', "Khreshchatyk 1\nKyiv\nMisto Kyiv\n01001"];
        yield 'a subdivision with a local code of its own' => [Address::of('UA', ['addressLine1' => 'Khreshchatyk 1', 'locality' => 'Kyiv', 'administrativeArea' => 'Kyiv', 'postalCode' => '01001']), 'UK-ua', "Khreshchatyk 1\nKyiv\nМісто Київ\n01001"];
        yield 'Japan in Japanese, written otherwise' => [$tokyo, 'JA-jp', "〒100-0001\n東京都千代田区\n千代田1-1\n山田 太郎"];
        yield 'a line of wide punctuation' => [Address::of('US', ['addressLine1' => '1 Main St', 'locality' => '「」']), null, '1 Main St'];
        yield 'a subdivision below an unknown one kept as given' => [Address::of('CN', ['addressLine1' => '1 Road', 'locality' => '福建省', 'administrativeArea' => 'Atlantis']), 'en', "1 Road\n福建省\nAtlantis"];
        yield 'many spaces' => [Address::of('FR', ['addressLine1' => '10   rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001']), null, "10 rue de Rivoli\n75001 Paris"];
        yield 'a hyphen kept inside' => [Address::of('BR', ['addressLine1' => 'Rua X, 10', 'locality' => 'Rio Branco', 'administrativeArea' => 'AC', 'postalCode' => '69900-000']), null, "Rua X, 10\nRio Branco-AC\n69900-000"];
    }

    #[DataProvider('written')]
    public function testAnAddressIsWrittenAsItsCountryWritesIt(Address $address, ?string $locale, string $text): void
    {
        self::assertSame($text, AddressFormatter::format($address, $locale));
        self::assertSame(explode("\n", $text), AddressFormatter::lines($address, $locale));
    }

    public function testAPostalLabelIsInCapitalsWithTheCountryAbroad(): void
    {
        $address = Address::of('US', ['givenName' => 'Ada', 'addressLine1' => '1 Main St', 'locality' => 'Austin', 'administrativeArea' => 'TX', 'postalCode' => '78701']);

        self::assertSame("Ada\n1 Main St\nAUSTIN, TX 78701\nÉTATS-UNIS", AddressFormatter::postalLabel($address, 'CA', 'fr'));
        self::assertSame("Ada\n1 Main St\nAUSTIN, TX 78701", AddressFormatter::postalLabel($address, 'us', 'fr'));
        self::assertSame("Ana\nAv. Larco 1\nLIMA\nPÉROU", AddressFormatter::postalLabel(Address::of('PE', ['givenName' => 'Ana', 'addressLine1' => 'Av. Larco 1', 'locality' => 'Lima']), 'CA', 'fr'));
        self::assertSame("ADA\nÉTÉ\nMONTRÉAL QC", AddressFormatter::postalLabel(Address::of('CA', ['givenName' => 'Ada', 'addressLine1' => 'été', 'locality' => 'Montréal', 'administrativeArea' => 'QC']), 'CA'));
    }
}
