<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\EmbeddingUsage;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\Fonts\Licence\FaceLicence;
use Phil\Fonts\Licence\Field;
use Phil\Fonts\Licence\LicenceRecord;
use Phil\Fonts\Licence\LicenceRegister;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenceRegister::class)]
#[CoversClass(LicenceRecord::class)]
#[CoversClass(FaceLicence::class)]
#[CoversClass(Field::class)]
#[CoversClass(InvalidLicenceRegister::class)]
final class LicenceRegisterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-fonts-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/sub', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['a.ttf', 'sub/B.WOFF', 'sub/c.ttc', 'readme.txt', 'sub/broken.otf'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }

        rmdir($this->directory.'/sub');
        rmdir($this->directory);
    }

    private static function ofl(string $family, int $fsType = 0): string
    {
        return FontBuilder::sfnt([
            'name' => FontBuilder::names([
                0 => '© 2026 Example', 1 => $family, 2 => 'Regular', 5 => 'Version 2.000', 6 => str_replace(' ', '', $family).'-Regular',
                7 => 'Trademark', 8 => 'Foundry', 9 => 'Designer', 11 => 'https://example.com/vendor',
                13 => 'This Font Software is licensed under the SIL Open Font License, Version 1.1.', 14 => 'https://openfontlicense.org',
            ]),
            'OS/2' => FontBuilder::os2(fsType: $fsType),
        ]);
    }

    public function testRecordsWhatEachFaceSaysAboutItsLicence(): void
    {
        $bytes = self::ofl('Example Sans', 0x0304);
        $record = LicenceRecord::of('fonts/example.ttf', FontFile::fromBytes($bytes));

        self::assertSame('fonts/example.ttf', $record->file);
        self::assertSame(hash('sha256', $bytes), $record->sha256);
        self::assertSame(Format::TrueType, $record->format);
        self::assertEquals(new FaceLicence(
            'Example Sans',
            'Regular',
            'ExampleSans-Regular',
            'Version 2.000',
            '© 2026 Example',
            'Trademark',
            'Foundry',
            'Designer',
            'https://example.com/vendor',
            'This Font Software is licensed under the SIL Open Font License, Version 1.1.',
            'https://openfontlicense.org',
            'OFL-1.1',
            EmbeddingUsage::PreviewAndPrint,
            false,
            true,
        ), $record->licence());
        self::assertSame([
            'file' => 'fonts/example.ttf',
            'sha256' => hash('sha256', $bytes),
            'format' => 'truetype',
            'faces' => [[
                'family' => 'Example Sans', 'subfamily' => 'Regular', 'postScriptName' => 'ExampleSans-Regular', 'version' => 'Version 2.000',
                'copyright' => '© 2026 Example', 'trademark' => 'Trademark', 'manufacturer' => 'Foundry', 'designer' => 'Designer',
                'vendorUrl' => 'https://example.com/vendor',
                'licence' => 'This Font Software is licensed under the SIL Open Font License, Version 1.1.',
                'licenceUrl' => 'https://openfontlicense.org', 'spdx' => 'OFL-1.1', 'embedding' => 'preview-and-print',
                'subsetting' => false, 'bitmapOnly' => true,
            ]],
        ], $record->toArray());
        self::assertEquals($record, LicenceRecord::fromArray($record->toArray()));
    }

    public function testScansADirectoryForFontFiles(): void
    {
        file_put_contents($this->directory.'/a.ttf', self::ofl('Alpha'));
        file_put_contents($this->directory.'/sub/B.WOFF', FontBuilder::woff(['name' => FontBuilder::names([1 => 'Beta'])]));
        file_put_contents($this->directory.'/sub/c.ttc', FontBuilder::collection([['name' => FontBuilder::names([1 => 'Gamma'])], ['name' => FontBuilder::names([1 => 'Delta'])]]));
        file_put_contents($this->directory.'/readme.txt', 'not a font');

        $register = LicenceRegister::scan($this->directory.'/');

        self::assertSame(['a.ttf', 'sub/B.WOFF', 'sub/c.ttc'], array_map(static fn (LicenceRecord $record): string => $record->file, $register->records()));
        self::assertSame('Alpha', $register->get('a.ttf')?->licence()->family);
        self::assertSame(Format::Woff, $register->get('sub/B.WOFF')?->format);
        self::assertSame(['Gamma', 'Delta'], array_map(static fn (FaceLicence $face): string => $face->family, $register->get('sub/c.ttc')->faces ?? []));
        self::assertNull($register->get('readme.txt'));
        self::assertEquals($register, LicenceRegister::scan($this->directory));
    }

    public function testAScanStopsAtAFileThatIsNoFont(): void
    {
        file_put_contents($this->directory.'/sub/broken.otf', 'not a font at all');

        $this->expectException(InvalidFont::class);

        LicenceRegister::scan($this->directory);
    }

    public function testAScanReadsWoff2ThroughTheDecompressorGiven(): void
    {
        file_put_contents($this->directory.'/a.ttf', FontBuilder::woff2([['name', FontBuilder::names([1 => 'Compressed']), 0]]));

        self::assertSame('Compressed', LicenceRegister::scan($this->directory, static fn (string $data): string => $data)->get('a.ttf')?->licence()->family);
    }

    public function testRoundTripsThroughJson(): void
    {
        $register = new LicenceRegister([
            LicenceRecord::of('z.ttf', FontFile::fromBytes(self::ofl('Zeta'))),
            LicenceRecord::of('a.ttf', FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'Ålpha'])]))),
        ]);
        $json = $register->toJson();

        self::assertStringStartsWith("{\n    \"fonts\": [\n        {\n            \"file\": \"a.ttf\",", $json);
        self::assertStringContainsString('"family": "Ålpha"', $json);
        self::assertStringContainsString('"licenceUrl": "https://openfontlicense.org"', $json);
        self::assertStringEndsWith("}\n", $json);
        self::assertEquals($register, LicenceRegister::fromJson($json));
        self::assertSame("{\n    \"fonts\": []\n}\n", new LicenceRegister()->toJson());
    }

    public function testReplacesTheRecordOfAFile(): void
    {
        $old = LicenceRecord::of('a.ttf', FontFile::fromBytes(self::ofl('Old')));
        $new = LicenceRecord::of('a.ttf', FontFile::fromBytes(self::ofl('New')));
        $other = LicenceRecord::of('b.ttf', FontFile::fromBytes(self::ofl('Other')));
        $register = new LicenceRegister([$other, $old]);

        self::assertSame([$old, $other], $register->records());
        self::assertSame([$new, $other], $register->with($new)->records());
        self::assertSame([$old, $other], $register->records());
        self::assertSame($old, new LicenceRegister([$other])->with($old)->get('a.ttf'));
    }

    public function testFindsTheFilesAddedRemovedOrChanged(): void
    {
        $record = static fn (string $file, string $family): LicenceRecord => LicenceRecord::of($file, FontFile::fromBytes(self::ofl($family)));
        $kept = new LicenceRegister([$record('same.ttf', 'Same'), $record('changed.ttf', 'Before'), $record('removed.ttf', 'Gone'), $record('1', 'Numeric')]);
        $scanned = new LicenceRegister([$record('same.ttf', 'Same'), $record('changed.ttf', 'After'), $record('added.ttf', 'New'), $record('1', 'Numeric')]);

        self::assertSame(['added.ttf', 'changed.ttf', 'removed.ttf'], $kept->changedFiles($scanned));
        self::assertSame(['added.ttf', 'changed.ttf', 'removed.ttf'], $scanned->changedFiles($kept));
        self::assertSame([], $kept->changedFiles($kept));
        self::assertSame(['1'], new LicenceRegister()->changedFiles(new LicenceRegister([$record('1', 'Numeric')])));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRegisters(): iterable
    {
        $face = ['family' => 'F', 'subfamily' => 'R', 'postScriptName' => null, 'version' => null, 'copyright' => null, 'trademark' => null,
            'manufacturer' => null, 'designer' => null, 'vendorUrl' => null, 'licence' => null, 'licenceUrl' => null, 'spdx' => null,
            'embedding' => 'installable', 'subsetting' => true, 'bitmapOnly' => false];
        $record = ['file' => 'a.ttf', 'sha256' => 'abc', 'format' => 'truetype', 'faces' => [$face]];
        $json = static fn (mixed $data): string => json_encode($data, \JSON_THROW_ON_ERROR);

        yield 'not JSON' => ['{', 'The licence register is not valid JSON: Syntax error'];
        yield 'not an object' => ['"fonts"', 'The field "fonts" must be a list.'];
        yield 'no fonts' => ['{}', 'The field "fonts" must be a list.'];
        yield 'fonts not a list' => [$json(['fonts' => ['a' => $record]]), 'The field "fonts" must be a list.'];
        yield 'fonts a string' => [$json(['fonts' => 'a']), 'The field "fonts" must be a list.'];
        yield 'record not an object' => [$json(['fonts' => ['a.ttf']]), 'Each record must be an object.'];
        yield 'unknown format' => [$json(['fonts' => [['format' => 'svg'] + $record]]), 'The field "format" names no font format.'];
        yield 'no faces' => [$json(['fonts' => [['faces' => []] + $record]]), 'The record of "a.ttf" has no face.'];
        yield 'face not an object' => [$json(['fonts' => [['faces' => ['F']] + $record]]), 'Each face must be an object.'];
        yield 'missing file' => [$json(['fonts' => [array_diff_key($record, ['file' => true])]]), 'The field "file" must be a string.'];
        yield 'sha256 not a string' => [$json(['fonts' => [['sha256' => 1] + $record]]), 'The field "sha256" must be a string.'];
        yield 'unknown embedding' => [$json(['fonts' => [['faces' => [['embedding' => 'never'] + $face]] + $record]]), 'The field "embedding" names no embedding usage.'];
        yield 'optional field missing' => [$json(['fonts' => [['faces' => [array_diff_key($face, ['spdx' => true])]] + $record]]), 'The field "spdx" must be a string or null.'];
        yield 'optional field mistyped' => [$json(['fonts' => [['faces' => [['designer' => 3] + $face]] + $record]]), 'The field "designer" must be a string.'];
        yield 'boolean mistyped' => [$json(['fonts' => [['faces' => [['subsetting' => 'yes'] + $face]] + $record]]), 'The field "subsetting" must be a boolean.'];
        yield 'boolean missing' => [$json(['fonts' => [['faces' => [array_diff_key($face, ['bitmapOnly' => true])]] + $record]]), 'The field "bitmapOnly" must be a boolean.'];
    }

    #[DataProvider('invalidRegisters')]
    public function testRefusesARegisterItCannotReadBack(string $json, string $message): void
    {
        $this->expectException(InvalidLicenceRegister::class);
        $this->expectExceptionMessageIsOrContains($message);

        LicenceRegister::fromJson($json);
    }

    public function testReadsBackNullOptionalFields(): void
    {
        $record = LicenceRecord::of('a.ttf', FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([])])));
        $face = LicenceRecord::fromArray($record->toArray())->licence();

        self::assertNull($face->postScriptName);
        self::assertNull($face->spdx);
        self::assertSame('', $face->family);
        self::assertSame(EmbeddingUsage::Installable, $face->embedding);
        self::assertTrue($face->subsetting);
        self::assertFalse($face->bitmapOnly);
    }
}
