<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Unit\Metadata;

use Override;
use Phil\Phone\Exception\InvalidMetadata;
use Phil\Phone\Metadata\MetadataImporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataImporter::class)]
#[CoversClass(InvalidMetadata::class)]
final class MetadataImporterTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-phone-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            is_dir($file) ? rmdir($file) : unlink($file);
        }

        rmdir($this->directory);
    }

    /**
     * The plans the new metadata no longer has are removed once the others are written; files the importer does not
     * write — the licence, the version — stay.
     */
    public function testTheDataIsReplaced(): void
    {
        file_put_contents($this->directory.'/QQ.php', 'a plan dropped since');
        file_put_contents($this->directory.'/ZZ.php', 'the old plan');
        file_put_contents($this->directory.'/VERSION', 'v9.0.0');

        new MetadataImporter()->import((string) file_get_contents(__DIR__.'/../Fixtures/metadata.xml'), $this->directory);

        $files = glob($this->directory.'/*');
        self::assertSame(['001-997.php', 'VERSION', 'WW.php', 'XX.php', 'YY.php', 'ZZ.php', 'country-codes.php'], array_map(basename(...), false === $files ? [] : $files));
        self::assertStringStartsWith('<?php', (string) file_get_contents($this->directory.'/ZZ.php'));
        self::assertSame('v9.0.0', file_get_contents($this->directory.'/VERSION'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function failedImports(): iterable
    {
        yield 'not xml' => ['<territory'];
        yield 'a territory without its country code after a good one' => ['<t><territory id="CA" countryCode="1"><generalDesc><nationalNumberPattern>\d+</nationalNumberPattern></generalDesc></territory><territory id="US"/></t>'];
    }

    /**
     * Metadata that is not libphonenumber's, found anywhere in the file, leaves every plan in place.
     */
    #[DataProvider('failedImports')]
    public function testAFailedImportLeavesTheDataInPlace(string $xml): void
    {
        file_put_contents($this->directory.'/CA.php', 'the old plan');
        file_put_contents($this->directory.'/country-codes.php', 'the old codes');

        try {
            new MetadataImporter()->import($xml, $this->directory);
            self::fail('The import went through.');
        } catch (InvalidMetadata) {
        }

        $files = glob($this->directory.'/*');
        self::assertSame(['CA.php', 'country-codes.php'], array_map(basename(...), false === $files ? [] : $files));
        self::assertSame(['the old plan', 'the old codes'], [file_get_contents($this->directory.'/CA.php'), file_get_contents($this->directory.'/country-codes.php')]);
    }

    public function testAPlanThatCannotBeWrittenLeavesTheOldOneWhole(): void
    {
        file_put_contents($this->directory.'/ZZ.php', 'the old plan');
        file_put_contents($this->directory.'/QQ.php', 'a plan dropped since');
        mkdir($this->directory.'/ZZ.php.new');

        try {
            new MetadataImporter()->import((string) file_get_contents(__DIR__.'/../Fixtures/metadata.xml'), $this->directory);
            self::fail('The import went through.');
        } catch (InvalidMetadata $invalidMetadata) {
            self::assertSame(\sprintf('The phone number plan "%s/ZZ.php" cannot be written.', $this->directory), $invalidMetadata->getMessage());
        }

        self::assertSame(['the old plan', 'a plan dropped since'], [file_get_contents($this->directory.'/ZZ.php'), file_get_contents($this->directory.'/QQ.php')]);
        self::assertDirectoryExists($this->directory.'/ZZ.php.new');
    }

    public function testAPlanWrittenButNotRenamedLeavesNoFileBehind(): void
    {
        mkdir($this->directory.'/ZZ.php');

        try {
            new MetadataImporter()->import((string) file_get_contents(__DIR__.'/../Fixtures/metadata.xml'), $this->directory);
            self::fail('The import went through.');
        } catch (InvalidMetadata $invalidMetadata) {
            self::assertSame(\sprintf('The phone number plan "%s/ZZ.php" cannot be written.', $this->directory), $invalidMetadata->getMessage());
        }

        self::assertFileDoesNotExist($this->directory.'/ZZ.php.new');
        self::assertDirectoryExists($this->directory.'/ZZ.php');
    }

    public function testATerritoryIsWrittenAsPhpData(): void
    {
        self::assertSame(5, new MetadataImporter()->import((string) file_get_contents(__DIR__.'/../Fixtures/metadata.xml'), $this->directory));

        self::assertSame([
            'id' => 'ZZ',
            'countryCode' => 999,
            'internationalPrefix' => '00',
            'preferredInternationalPrefix' => null,
            'nationalPrefix' => '0',
            'nationalPrefixForParsing' => '0',
            'nationalPrefixTransformRule' => null,
            'leadingDigits' => null,
            'preferredExtnPrefix' => ' poste ',
            'general' => ['pattern' => '[1-9]\d{5}', 'lengths' => [5, 6, 7, 8, 9]],
            'types' => [
                'mobile' => ['pattern' => '6\d{5}', 'lengths' => [9, 8, 6, 7]],
                'voicemail' => ['pattern' => '5\d{4,5}', 'lengths' => [6, 5]],
            ],
            'formats' => [
                ['pattern' => '(\d{3})(\d{3})', 'leadingDigits' => '1|2', 'format' => '$1 $2', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2'],
                ['pattern' => '(\d{2})(\d{4})', 'leadingDigits' => null, 'format' => '$1-$2', 'nationalRule' => '(0 $1)', 'intlFormat' => null],
                ['pattern' => '(\d)(\d{5})', 'leadingDigits' => null, 'format' => '$1 $2', 'nationalRule' => '0$1', 'intlFormat' => '$1.$2'],
                ['pattern' => '(\d+)', 'leadingDigits' => null, 'format' => '', 'nationalRule' => '0$1', 'intlFormat' => ''],
            ],
        ], require $this->directory.'/ZZ.php');

        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n\n// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\\Phone\\Metadata\\MetadataImporter.\nreturn ['id' => 'ZZ', ", (string) file_get_contents($this->directory.'/ZZ.php'));
    }

    public function testAMainCountryComesFirstAndNumbersOfNoCountryHaveTheirOwnFile(): void
    {
        new MetadataImporter()->import((string) file_get_contents(__DIR__.'/../Fixtures/metadata.xml'), $this->directory);

        self::assertSame([996 => ['WW'], 997 => ['001'], 998 => ['YY', 'XX'], 999 => ['ZZ']], require $this->directory.'/country-codes.php');

        self::assertSame($this->territory(['id' => 'YY', 'countryCode' => 998, 'nationalPrefix' => '8', 'nationalPrefixForParsing' => '9', 'nationalPrefixTransformRule' => '5$1', 'leadingDigits' => '7', 'general' => ['pattern' => '\d{4}', 'lengths' => []]]), require $this->directory.'/YY.php');
        self::assertSame($this->territory(['id' => 'XX', 'countryCode' => 998]), require $this->directory.'/XX.php');
        self::assertSame($this->territory(['id' => 'WW', 'countryCode' => 996, 'general' => ['pattern' => '', 'lengths' => []]]), require $this->directory.'/WW.php');
        self::assertSame($this->territory(['id' => '001', 'countryCode' => 997, 'general' => ['pattern' => "it's\\d", 'lengths' => []]]), require $this->directory.'/001-997.php');
        self::assertFileDoesNotExist($this->directory.'/001.php');
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function territory(array $values): array
    {
        return [...[
            'id' => '',
            'countryCode' => 0,
            'internationalPrefix' => null,
            'preferredInternationalPrefix' => null,
            'nationalPrefix' => null,
            'nationalPrefixForParsing' => null,
            'nationalPrefixTransformRule' => null,
            'leadingDigits' => null,
            'preferredExtnPrefix' => null,
            'general' => ['pattern' => '\d+', 'lengths' => []],
            'types' => [],
            'formats' => [],
        ], ...$values];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badMetadata(): iterable
    {
        yield 'not xml' => ['<territory', 'The phone number metadata is not XML.'];
        yield 'no identifier' => ['<t><territory countryCode="1"/></t>', 'The territory "" of the phone number metadata has no identifier or country code.'];
        yield 'no country code' => ['<t><territory id="CA" countryCode="+1"/></t>', 'The territory "CA" of the phone number metadata has no identifier or country code.'];
    }

    #[DataProvider('badMetadata')]
    public function testMetadataThatIsNotLibphonenumbersIsRefused(string $xml, string $message): void
    {
        $this->expectException(InvalidMetadata::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MetadataImporter()->import($xml, $this->directory);
    }
}
