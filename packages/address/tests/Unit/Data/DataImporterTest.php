<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Unit\Data;

use Override;
use Phil\Address\Data\DataImporter;
use Phil\Address\Exception\InvalidData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataImporter::class)]
#[CoversClass(InvalidData::class)]
final class DataImporterTest extends TestCase
{
    private const array GENERIC = [
        'format' => "%givenName %familyName\n%addressLine1\n%locality",
        'required_fields' => ['addressLine1', 'locality'],
        'uppercase_fields' => ['locality'],
        'administrative_area_type' => 'province',
        'locality_type' => 'city',
        'dependent_locality_type' => 'suburb',
        'postal_code_type' => 'postal',
    ];

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-address-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/formats', recursive: true);
        mkdir($this->directory.'/subdivisions');
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['formats', 'subdivisions'] as $folder) {
            $files = glob($this->directory.'/'.$folder.'/*');
            array_map(unlink(...), false === $files ? [] : $files);
            rmdir($this->directory.'/'.$folder);
        }

        rmdir($this->directory);
    }

    public function testFormatsAndSubdivisionsAreWrittenAsPhpData(): void
    {
        $definitions = [
            'CA' => [
                'format' => "%addressLine1\n%locality %administrativeArea %postalCode",
                'required_fields' => ['addressLine1', 'unknownField', 'locality'],
                'uppercase_fields' => ['locality'],
                'postal_code_pattern' => '[A-Z]\d[A-Z] ?\d[A-Z]\d',
                'subdivision_data_fields' => ['administrativeArea'],
                'locale' => '',
            ],
            'JP' => ['local_format' => "〒%postalCode\n%addressLine1", 'locale' => 'ja', 'postal_code_prefix' => 'JP-', 'administrative_area_type' => 'prefecture', 'locality_type' => 'district', 'dependent_locality_type' => 'neighborhood', 'postal_code_type' => 'zip', 'default_values' => ['locality' => 'Tokyo', 'odd' => 3], 'required_fields' => 'none', 'subdivision_data_fields' => 'none'],
        ];
        $subdivisions = [
            'CA' => '{"country_code": "CA", "locale": "fr", "subdivisions": {"QC": {"code": "QC", "local_code": "QC", "name": "Quebec", "local_name": "Québec", "iso_code": "CA-QC", "postal_code_pattern": "[GHJ]", "has_children": true}, "Hokkaido": [], "01": {"name": "Hokkaido \'s"}, "7": {"name": "Seven"}, "8": []}}',
        ];

        self::assertSame(4, new DataImporter()->import($definitions, self::GENERIC, $subdivisions, $this->directory));

        self::assertSame([
            'country' => 'CA',
            'format' => "%addressLine1\n%locality %administrativeArea %postalCode",
            'localFormat' => null,
            'locale' => null,
            'required' => ['addressLine1', 'locality'],
            'uppercase' => ['locality'],
            'postalCodePattern' => '[A-Z]\d[A-Z] ?\d[A-Z]\d',
            'postalCodePrefix' => null,
            'administrativeAreaType' => 'province',
            'localityType' => 'city',
            'dependentLocalityType' => 'suburb',
            'postalCodeType' => 'postal',
            'subdivisionDepth' => 1,
            'defaults' => [],
        ], require $this->directory.'/formats/CA.php');

        $japan = require $this->directory.'/formats/JP.php';
        self::assertIsArray($japan);
        self::assertSame([self::GENERIC['format'], "〒%postalCode\n%addressLine1", 'ja', 'JP-', 'prefecture', 'district', 'neighborhood', 'zip', ['locality' => 'Tokyo'], [], 0], [$japan['format'], $japan['localFormat'], $japan['locale'], $japan['postalCodePrefix'], $japan['administrativeAreaType'], $japan['localityType'], $japan['dependentLocalityType'], $japan['postalCodeType'], $japan['defaults'], $japan['required'], $japan['subdivisionDepth']]);

        $generic = require $this->directory.'/formats/ZZ.php';
        self::assertIsArray($generic);
        self::assertSame(['ZZ', ['addressLine1', 'locality']], [$generic['country'], $generic['required']]);

        self::assertSame(['locale' => 'fr', 'subdivisions' => [
            ['id' => 'QC', 'code' => 'QC', 'localCode' => 'QC', 'name' => 'Quebec', 'localName' => 'Québec', 'isoCode' => 'CA-QC', 'postalCodePattern' => '[GHJ]', 'hasChildren' => true],
            ['id' => 'Hokkaido', 'code' => 'Hokkaido', 'localCode' => null, 'name' => 'Hokkaido', 'localName' => null, 'isoCode' => null, 'postalCodePattern' => null, 'hasChildren' => false],
            ['id' => '01', 'code' => "Hokkaido 's", 'localCode' => null, 'name' => "Hokkaido 's", 'localName' => null, 'isoCode' => null, 'postalCodePattern' => null, 'hasChildren' => false],
            ['id' => '7', 'code' => 'Seven', 'localCode' => null, 'name' => 'Seven', 'localName' => null, 'isoCode' => null, 'postalCodePattern' => null, 'hasChildren' => false],
            ['id' => '8', 'code' => '8', 'localCode' => null, 'name' => '8', 'localName' => null, 'isoCode' => null, 'postalCodePattern' => null, 'hasChildren' => false],
        ]], require $this->directory.'/subdivisions/CA.php');
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n\n// Generated from commerceguys/addressing's data (MIT License) by Phil\\Address\\Data\\DataImporter.\nreturn [", (string) file_get_contents($this->directory.'/subdivisions/CA.php'));
    }

    public function testNestedSubdivisionsAreNamedByTheirParents(): void
    {
        new DataImporter()->import([], self::GENERIC, ['BR-AC' => '{"subdivisions": {"Rio Branco": []}}'], $this->directory);

        self::assertSame(['locale' => null, 'subdivisions' => [['id' => 'Rio Branco', 'code' => 'Rio Branco', 'localCode' => null, 'name' => 'Rio Branco', 'localName' => null, 'isoCode' => null, 'postalCodePattern' => null, 'hasChildren' => false]]], require $this->directory.'/subdivisions/BR-AC.php');
    }

    /**
     * @return iterable<string, array{array<string, array<string, mixed>>, array<string, string>, string}>
     */
    public static function badData(): iterable
    {
        yield 'a country that is no code' => [['Canada' => ['format' => '%locality']], [], 'The address format of "Canada" has no country code or no format.'];
        yield 'a country with more before' => [['xCA' => ['format' => '%locality']], [], 'The address format of "xCA" has no country code or no format.'];
        yield 'a country with more after' => [['CAx' => ['format' => '%locality']], [], 'The address format of "CAx" has no country code or no format.'];
        yield 'subdivisions that are no JSON' => [[], ['CA' => '{'], 'The subdivisions "CA" are not commerceguys/addressing\'s.'];
        yield 'subdivisions without list' => [[], ['CA' => '{"country_code": "CA"}'], 'The subdivisions "CA" are not commerceguys/addressing\'s.'];
        yield 'a file name that leaves the folder' => [[], ['CA-../x' => '{"subdivisions": {}}'], 'The subdivisions "CA-../x" are not commerceguys/addressing\'s.'];
        yield 'a file name with more before' => [[], ['xCA' => '{"subdivisions": {}}'], 'The subdivisions "xCA" are not commerceguys/addressing\'s.'];
        yield 'a file name without country' => [[], ['x' => '{"subdivisions": {}}'], 'The subdivisions "x" are not commerceguys/addressing\'s.'];
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, string>               $subdivisions
     */
    #[DataProvider('badData')]
    public function testDataThatIsNotCommerceguysIsRefused(array $definitions, array $subdivisions, string $message): void
    {
        $this->expectException(InvalidData::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DataImporter()->import($definitions, self::GENERIC, $subdivisions, $this->directory);
    }

    public function testTheImportReplacesTheFoldersAlreadyThere(): void
    {
        file_put_contents($this->directory.'/formats/XX.php', '<?php return [];');
        file_put_contents($this->directory.'/subdivisions/XX.php', '<?php return [];');

        self::assertSame(2, new DataImporter()->import([], self::GENERIC, ['CA' => '{"subdivisions": {}}'], $this->directory));

        self::assertSame(['ZZ.php'], $this->names('formats'));
        self::assertSame(['CA.php'], $this->names('subdivisions'));
        self::assertSame(['formats', 'subdivisions'], $this->names(''));
    }

    public function testDataRefusedHalfwayLeaveTheFoldersAsTheyWere(): void
    {
        file_put_contents($this->directory.'/formats/CA.php', 'kept');

        try {
            new DataImporter()->import(['CA' => ['format' => '%locality']], self::GENERIC, ['CA' => '{'], $this->directory);
            self::fail('The import should have been refused.');
        } catch (InvalidData) {
        }

        self::assertSame('kept', file_get_contents($this->directory.'/formats/CA.php'));
        self::assertSame(['CA.php'], $this->names('formats'));
        self::assertSame(['formats', 'subdivisions'], $this->names(''));
    }

    public function testAGenericFormatWithoutFormatIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The address format of "ZZ" has no country code or no format.');

        new DataImporter()->import([], [], [], $this->directory);
    }

    /**
     * The files and folders in a folder of the import's, by name.
     *
     * @return list<string>
     */
    private function names(string $folder): array
    {
        $entries = scandir($this->directory.'/'.$folder);

        return array_values(array_diff(false === $entries ? [] : $entries, ['.', '..']));
    }
}
