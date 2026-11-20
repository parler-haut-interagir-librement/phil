<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Package;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Scorm\ScormManifest;
use Phil\ElearningPackages\Tests\Fixture\MemoryArchive;
use Phil\ElearningPackages\Tests\Fixture\Packages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageReader::class)]
#[CoversClass(Package::class)]
#[CoversClass(PackageFormat::class)]
final class PackageReaderTest extends TestCase
{
    public function testAScormPackageIsReadWithItsFiles(): void
    {
        $package = new PackageReader()->readFile(Packages::zip(Packages::scorm12Files()));

        self::assertSame(PackageFormat::Scorm12, $package->format);
        self::assertInstanceOf(ScormManifest::class, $package->manifest);
        self::assertSame('com.example.safety', $package->identifier());
        self::assertSame('Workshop safety', $package->title());
        self::assertSame(array_keys(Packages::scorm12Files()), $package->files);
        self::assertSame([], $package->warnings);
    }

    public function testACmi5PackageIsReadInTheLanguageAsked(): void
    {
        $package = new PackageReader(language: 'fr')->read(new MemoryArchive(Packages::cmi5Files()));

        self::assertSame(PackageFormat::Cmi5, $package->format);
        self::assertInstanceOf(Cmi5Course::class, $package->manifest);
        self::assertSame('https://example.org/courses/navigation', $package->identifier());
        self::assertSame("Navigation à l'estime", $package->title());
        self::assertSame(['cmi5.xml', 'welcome/index.html', 'tides/start.html'], $package->files);
        self::assertSame([], $package->warnings);
    }

    public function testCmi5WinsOverScormWhenAPackageHoldsBoth(): void
    {
        $package = new PackageReader()->read(new MemoryArchive([...Packages::cmi5Files(), 'imsmanifest.xml' => '<not-read']));

        self::assertSame(PackageFormat::Cmi5, $package->format);
    }

    public function testWhatTheManifestNamesButThePackageDoesNotHoldIsNoted(): void
    {
        $scorm = new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => Packages::SCORM12, 'content/intro/index.html' => '']));

        self::assertSame([
            'The launch file "content/tools/saws.html" of the item "saws" is not in the package.',
            'The launch file "content/tools/chisels care.html" of the item "chisel-care" is not in the package.',
            'The launch file "content/tools/saws.html" of the item "hidden" is not in the package.',
            'The file "content/common/style.css" of the resource "res-common" is not in the package.',
        ], $scorm->warnings);

        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">'
            .'<item identifier="a" identifierref="r1"/><item identifier="b" identifierref="nowhere"/><item identifier="c"/><item identifier="d" identifierref="r2"/>'
            .'</organization></organizations><resources><resource identifier="r1" href="a.html#top"><file href="a.html"/><file href="b.html?x"/></resource>'
            .'<resource identifier="r2"/></resources></manifest>';
        self::assertSame([
            'The launch file "a.html" of the item "a" is not in the package.',
            'The item "b" names the resource "nowhere", which the manifest does not describe.',
            'The file "a.html" of the resource "r1" is not in the package.',
            'The file "b.html" of the resource "r1" is not in the package.',
        ], new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => $xml]))->warnings);
        self::assertSame([
            'The item "b" names the resource "nowhere", which the manifest does not describe.',
            'The file "b.html" of the resource "r1" is not in the package.',
        ], new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => $xml, 'a.html' => '']))->warnings);

        $cmi5 = new PackageReader()->read(new MemoryArchive(['cmi5.xml' => Packages::CMI5, 'welcome/index.html' => '']));
        self::assertSame(['The launch file "tides/start.html" of the assignable unit "https://example.org/aus/tides" is not in the package.'], $cmi5->warnings);
    }

    public function testEveryOrganizationIsChecked(): void
    {
        $package = new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => Packages::SCORM2004]));

        self::assertSame(PackageFormat::Scorm2004, $package->format);
        self::assertSame('First aid', $package->title());
        self::assertSame([
            'The launch file "sco/start.html" of the item "u1" is not in the package.',
            'The launch file "sco/start.html" of the item "a1" is not in the package.',
        ], $package->warnings);
    }

    public function testALongResourceReferenceIsShownShort(): void
    {
        $reference = str_repeat('r', 200);
        $xml = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">'
            .'<item identifier="a" identifierref="'.$reference.'"/></organization></organizations></manifest>';

        self::assertSame(['The item "a" names the resource "'.str_repeat('r', 120).'", which the manifest does not describe.'], new PackageReader()->read(new MemoryArchive(['imsmanifest.xml' => $xml]))->warnings);
    }

    public function testAPackageWithoutAManifestIsRefusedAndClosed(): void
    {
        $archive = new MemoryArchive(['index.html' => '']);
        $opener = new class($archive) implements PackageOpener {
            public ?PackageLimits $limits = null;

            public function __construct(
                private readonly PackageArchive $archive,
            ) {
            }

            public function open(string $path, PackageLimits $limits): PackageArchive
            {
                $this->limits = $limits;

                return $this->archive;
            }
        };
        $limits = new PackageLimits(items: 3);

        try {
            new PackageReader($limits, null, $opener)->readFile('any.zip');
            self::fail('The package is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The package holds none of cmi5.xml, imsmanifest.xml and tincan.xml at its root.', $packageRefused->getMessage());
        }

        self::assertTrue($archive->closed);
        self::assertSame($limits, $opener->limits);
    }

    public function testTheLimitsApplyToTheManifests(): void
    {
        $limits = new PackageLimits(manifestBytes: 100);

        foreach ([['cmi5.xml' => Packages::CMI5], ['imsmanifest.xml' => Packages::SCORM12]] as $files) {
            try {
                new PackageReader($limits)->read(new MemoryArchive($files));
                self::fail('The manifest is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertStringEndsWith('is larger than 100 bytes.', $packageRefused->getMessage());
            }
        }

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('is larger than 100 bytes.');
        new PackageReader($limits)->readStructure(Packages::CMI5);
    }

    public function testAStructureAloneNotesTheUnitsThatNameFiles(): void
    {
        $package = new PackageReader(language: 'fr-CA')->readStructure(Packages::CMI5);

        self::assertSame(PackageFormat::Cmi5, $package->format);
        self::assertSame("Navigation à l'estime", $package->title());
        self::assertSame([], $package->files);
        self::assertSame([
            'The assignable unit "https://example.org/aus/welcome" names a file, "welcome/index.html?mode=short", of a package that is not given: a course structure alone names absolute URLs.',
            'The assignable unit "https://example.org/aus/tides" names a file, "tides/start.html", of a package that is not given: a course structure alone names absolute URLs.',
        ], $package->warnings);
        self::assertSame([], new PackageReader()->readStructure('<courseStructure><course id="c"/><au id="a"><url>https://example.org/a</url></au></courseStructure>')->warnings);
        self::assertSame(
            ['The assignable unit "a" names a file, "'.str_repeat('a', 120).'", of a package that is not given: a course structure alone names absolute URLs.'],
            new PackageReader()->readStructure('<courseStructure><course id="c"/><au id="a"><url>'.str_repeat('a', 200).'</url></au></courseStructure>')->warnings,
        );
    }

    public function testAUnitLeavingThePackageIsRefused(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('holds an empty, "." or ".." segment');

        new PackageReader()->read(new MemoryArchive(['cmi5.xml' => '<courseStructure><course id="c"/><au id="a"><url>../a.html</url></au></courseStructure>']));
    }

    public function testEachFormatNamesItsManifest(): void
    {
        self::assertSame('cmi5.xml', PackageFormat::Cmi5->manifest());
        self::assertSame('imsmanifest.xml', PackageFormat::Scorm12->manifest());
        self::assertSame('imsmanifest.xml', PackageFormat::Scorm2004->manifest());
    }
}
