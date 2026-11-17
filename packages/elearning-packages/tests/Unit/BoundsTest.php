<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Closure;
use Phil\ElearningPackages\Archive\ZipPackage;
use Phil\ElearningPackages\Cmi5\Cmi5Au;
use Phil\ElearningPackages\Cmi5\Cmi5Block;
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormItem;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Tests\Fixture\MemoryArchive;
use Phil\ElearningPackages\Warnings;
use Phil\ElearningPackages\Xml\XmlDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ValueError;
use ZipArchive;

/**
 * The exact bounds of each check, inclusive, and the refusals that show a value in a few characters, whatever their
 * script.
 */
#[CoversClass(ZipPackage::class)]
#[CoversClass(Cmi5CourseReader::class)]
#[CoversClass(ScormManifestReader::class)]
#[CoversClass(PackagePath::class)]
#[CoversClass(PackageReader::class)]
#[CoversClass(Warnings::class)]
#[CoversClass(XmlDocument::class)]
#[CoversClass(Cmi5Course::class)]
#[CoversClass(ScormItem::class)]
final class BoundsTest extends TestCase
{
    public function testAnArchiveOfTheLimitIsRead(): void
    {
        $path = $this->zip(static fn (ZipArchive $zip): bool => $zip->addFromString('a', 'a'));

        self::assertSame(['a'], ZipPackage::open($path, new PackageLimits(archiveBytes: (int) filesize($path)))->files());
    }

    public function testANumericFileNameStaysText(): void
    {
        $package = ZipPackage::open($this->zip(static fn (ZipArchive $zip): bool => $zip->addFromString('123', 'x')), new PackageLimits());

        self::assertSame(['123'], $package->files());
    }

    public function testAMissingFileIsNamedShortWhateverItsScript(): void
    {
        $package = ZipPackage::open($this->zip(static fn (ZipArchive $zip): bool => $zip->addFromString('a', 'x')), new PackageLimits());

        try {
            $package->read(str_repeat('é', 130));
            self::fail('The file is read.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The package has no file "'.str_repeat('é', 120).'".', $packageRefused->getMessage());
        }
    }

    public function testNamesThatDifferByTheCaseOfAnAccentAreRefused(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The package holds "École.html" and "école.html", which differ only by the case of their letters.');

        ZipPackage::open($this->zip(static function (ZipArchive $zip): void {
            $zip->addFromString('École.html', 'a');
            $zip->addFromString('école.html', 'b');
        }), new PackageLimits());
    }

    public function testTheRatioIsInclusive(): void
    {
        $size = 1024 * 1024 + 1;
        $stored = $this->zip(static function (ZipArchive $zip) use ($size): void {
            $zip->addFromString('stored.bin', str_repeat('s', $size));
            $zip->setCompressionName('stored.bin', ZipArchive::CM_STORE);
        });

        self::assertSame(['stored.bin'], ZipPackage::open($stored, new PackageLimits(ratio: 1))->files());
    }

    public function testAClosedArchiveIsReleased(): void
    {
        $package = ZipPackage::open($this->zip(static fn (ZipArchive $zip): bool => $zip->addFromString('a', 'x')), new PackageLimits());
        $zip = Closure::bind(static fn (ZipPackage $package): ZipArchive => $package->zip, null, ZipPackage::class)($package);
        $package->close();

        $this->expectException(ValueError::class);
        $zip->count();
    }

    public function testAnEntryLongerThanItsHeaderSaysIsRefusedForItsSize(): void
    {
        $size = 65_536 + 100;
        $path = $this->zip(static function (ZipArchive $zip) use ($size): void {
            $zip->addFromString('a.txt', str_repeat('a', $size));
            $zip->setCompressionName('a.txt', ZipArchive::CM_STORE);
        });
        $bytes = (string) file_get_contents($path);
        $central = strpos($bytes, "PK\x01\x02");

        if (false === $central) {
            throw new RuntimeException('The archive has no central directory.');
        }

        // The uncompressed size of the central directory entry says exactly one chunk of the reading.
        file_put_contents($path, substr_replace($bytes, pack('V', 65_536), $central + 24, 4));
        $package = ZipPackage::open($path, new PackageLimits());

        try {
            $package->read('a.txt');
            self::fail('The file is read.');
        } catch (PackageRefused $packageRefused) {
            self::assertStringNotContainsString('checksum', $packageRefused->getMessage());
        }
    }

    public function testTheIdentifiersAndUrlsOfTheLimitAreRead(): void
    {
        $iri = str_repeat('c', 255);
        $url = str_repeat('u', 2048);
        $course = new Cmi5CourseReader()->read('<courseStructure><course id="'.$iri.'"/><au id="a"><url>'.$url.'</url></au></courseStructure>');

        self::assertSame($iri, $course->id);
        self::assertInstanceOf(Cmi5Au::class, $course->children[0]);
        self::assertSame($url, $course->children[0]->url);

        $manifest = new ScormManifestReader()->read('<manifest identifier="'.str_repeat('m', 255).'"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"><item identifier="i"/></organization></organizations></manifest>');
        self::assertSame(str_repeat('m', 255), $manifest->identifier);
    }

    public function testTheTitlesDescriptionsAndLanguagesOfAStructureAreRead(): void
    {
        $course = new Cmi5CourseReader(language: 'fr')->read(<<<'XML'
            <courseStructure><course id="c"/>
              <block id="b"><title><langstring>Block</langstring></title><description><langstring>About the block</langstring></description>
                <au id="a"><title><langstring lang="EN">Unit</langstring><langstring lang="FR-CA">Unité</langstring></title><url>a.html</url></au>
              </block>
            </courseStructure>
            XML);

        $block = $course->children[0];
        self::assertInstanceOf(Cmi5Block::class, $block);
        self::assertSame('About the block', $block->description);
        self::assertInstanceOf(Cmi5Au::class, $block->children[0]);
        self::assertSame('Unité', $block->children[0]->title);
    }

    public function testARefusedCmi5ValueIsShownShort(): void
    {
        foreach (['moveOn' => 'The moveOn "', 'launchMethod' => 'The launchMethod "'] as $attribute => $start) {
            try {
                new Cmi5CourseReader()->read('<courseStructure><course id="c"/><au id="a" '.$attribute.'="'.str_repeat('é', 50).'"><url>a</url></au></courseStructure>');
                self::fail('The value is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertSame($start.str_repeat('é', 40).'" of the assignable unit "a" is not one cmi5 defines.', $packageRefused->getMessage());
            }
        }

        try {
            new ScormManifestReader()->read('<manifest identifier="m"><metadata><schemaversion>'.str_repeat('é', 70).'</schemaversion></metadata></manifest>');
            self::fail('The version is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The SCORM manifest names no version the reader knows: "'.str_repeat('é', 60).'".', $packageRefused->getMessage());
        }
    }

    public function testTheScoresAndThresholdsOfTheLimitAreRead(): void
    {
        $manifest = new ScormManifestReader()->read(<<<'XML'
            <manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">
              <item identifier="low"><masteryscore>0</masteryscore><completionThreshold>0</completionThreshold></item>
              <item identifier="high"><masteryscore>100</masteryscore><completionThreshold>1</completionThreshold></item>
              <item identifier="under"><masteryscore>-1</masteryscore><completionThreshold>-0.5</completionThreshold></item>
            </organization></organizations></manifest>
            XML);
        [$low, $high, $under] = $manifest->organization()->items;

        self::assertSame([0.0, 0.0], [$low->masteryScore, $low->completionThreshold]);
        self::assertSame([1.0, 1.0], [$high->masteryScore, $high->completionThreshold]);
        self::assertSame([null, null], [$under->masteryScore, $under->completionThreshold]);
    }

    public function testTheItemsOfTheLimitAreRead(): void
    {
        $items = str_repeat('<item identifier="x"/>', 3);
        $manifest = new ScormManifestReader(new PackageLimits(items: 3))->read('<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">'.$items.'</organization></organizations></manifest>');

        self::assertCount(3, $manifest->organization()->items);
    }

    public function testTheFilesAndDependenciesAreListsWithoutGaps(): void
    {
        $manifest = new ScormManifestReader()->read('<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"><item identifier="i" identifierref="r"/></organization></organizations>'
            .'<resources><resource identifier="r" href="a.html"><file/><file href="b.html"/><dependency/><dependency identifierref="s"/></resource></resources></manifest>');

        self::assertSame(['b.html'], $manifest->resources['r']->files);
        self::assertSame(['s'], $manifest->resources['r']->dependencies);
    }

    public function testAPathIsShownShortWhateverItsScript(): void
    {
        try {
            PackagePath::check('/'.str_repeat('é', 200));
            self::fail('The path is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The path "/'.str_repeat('é', 119).'" is absolute, or holds a backslash, a colon or a control character.', $packageRefused->getMessage());
        }
    }

    public function testTheReadingGoesOnAfterAnItemOrUnitItSkips(): void
    {
        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">'
            .'<item identifier="b" identifierref="nowhere"/><item identifier="w" identifierref="web"/><item identifier="a" identifierref="r1"/>'
            .'</organization></organizations><resources><resource identifier="web" href="https://example.org/a"/><resource identifier="r1" href="a.html"/></resources></manifest>';

        self::assertSame([
            'The item "b" names the resource "nowhere", which the manifest does not describe.',
            'The launch file "a.html" of the item "a" is not in the package.',
        ], new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => $xml]))->warnings);

        $cmi5 = '<courseStructure><course id="c"/><block id="b"><au id="a1"><url>https://example.org/a</url></au></block><au id="a2"><url>a.html</url></au></courseStructure>';
        self::assertSame(['The launch file "a.html" of the assignable unit "a2" is not in the package.'], new PackageReader()->read(new MemoryArchive(['cmi5.xml' => $cmi5]))->warnings);
    }

    public function testTheNotesShowLongValuesShortWhateverTheirScript(): void
    {
        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">'
            .'<item identifier="a" identifierref="'.str_repeat('é', 130).'"/></organization></organizations></manifest>';
        self::assertSame(['The item "a" names the resource "'.str_repeat('é', 120).'", which the manifest does not describe.'], new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => $xml]))->warnings);

        self::assertSame(
            ['The assignable unit "a" names a file, "'.str_repeat('é', 120).'", of a package that is not given: a course structure alone names absolute URLs.'],
            new PackageReader()->readStructure('<courseStructure><course id="c"/><au id="a"><url>'.str_repeat('é', 130).'</url></au></courseStructure>')->warnings,
        );
    }

    public function testANumericNoteStaysText(): void
    {
        $warnings = new Warnings();
        $warnings->add('7');

        self::assertSame(['7'], $warnings->all());
    }

    public function testADocumentOfTheLimitIsReadAndTheErrorsOfLibxmlAreCleared(): void
    {
        $xml = '<a>'.str_repeat('a', 93).'</a>';
        self::assertSame('a', XmlDocument::root($xml, \strlen($xml), 'The test')->localName);

        $previous = libxml_use_internal_errors(true);

        try {
            XmlDocument::root('<a>', 100, 'The test');
            self::fail('The document is accepted.');
        } catch (PackageRefused) {
            self::assertSame([], libxml_get_errors());
            self::assertTrue(libxml_use_internal_errors(true));
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testTheDefaultsOfTheValues(): void
    {
        self::assertFalse(new Cmi5Course('c', 'C', [])->hasObjectives);
        self::assertTrue(new ScormItem('i', 'I')->visible);
    }

    /**
     * @param callable(ZipArchive): mixed $fill
     */
    private function zip(callable $fill): string
    {
        $path = sys_get_temp_dir().'/phil-elearning-bounds-'.bin2hex(random_bytes(6)).'.zip';
        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('The archive cannot be written.');
        }

        $fill($zip);
        $zip->close();

        return $path;
    }
}
