<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Cmi5\Cmi5Au;
use Phil\ElearningPackages\Cmi5\Cmi5Block;
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Cmi5\LaunchMethod;
use Phil\ElearningPackages\Cmi5\MoveOn;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Scorm\ScormItem;
use Phil\ElearningPackages\Scorm\ScormManifest;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Scorm\ScormOrganization;
use Phil\ElearningPackages\Scorm\ScormResource;
use Phil\ElearningPackages\Tests\Fixture\Packages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScormManifestReader::class)]
#[CoversClass(Cmi5CourseReader::class)]
#[CoversClass(ScormManifest::class)]
#[CoversClass(ScormOrganization::class)]
#[CoversClass(ScormItem::class)]
#[CoversClass(ScormResource::class)]
#[CoversClass(Cmi5Course::class)]
#[CoversClass(Cmi5Block::class)]
#[CoversClass(Cmi5Au::class)]
final class ReadersTest extends TestCase
{
    public function testAScorm12ManifestIsRead(): void
    {
        $manifest = new ScormManifestReader()->read(Packages::SCORM12);

        self::assertSame('com.example.safety', $manifest->identifier);
        self::assertSame(PackageFormat::Scorm12, $manifest->format);
        self::assertSame('1.2', $manifest->schemaVersion);
        self::assertSame('org-safety', $manifest->defaultOrganization);
        self::assertNull($manifest->sequencingCollection);

        $organization = $manifest->organization();
        self::assertSame('Workshop safety', $organization->title);
        self::assertNull($organization->sequencing);
        self::assertSame(['intro', 'part-1', 'outro'], array_map(static fn (ScormItem $item): string => $item->identifier, $organization->items));
        self::assertTrue($organization->items[0]->isLeaf());
        self::assertSame('res-intro', $organization->items[0]->resource);

        $saws = $organization->items[1]->children[0];
        self::assertFalse($organization->items[1]->isLeaf());
        self::assertSame('Saws', $saws->title);
        self::assertSame('?page=2', $saws->parameters);
        self::assertSame(0.8, $saws->masteryScore);
        self::assertSame('intro', $saws->prerequisites);
        self::assertTrue($saws->visible);
        self::assertFalse($organization->items[1]->children[2]->visible);
        self::assertNull($organization->items[1]->children[3]->resource);

        self::assertSame(['res-intro', 'res-saws', 'res-chisels', 'res-web', 'res-common'], array_keys($manifest->resources));
        self::assertSame('content/intro/index.html', $manifest->resources['res-intro']->href);
        self::assertTrue($manifest->resources['res-intro']->isSco());
        self::assertSame(['content/intro/index.html'], $manifest->resources['res-intro']->files);
        self::assertSame(['res-common'], $manifest->resources['res-intro']->dependencies);
        self::assertSame('content/tools/saws.html?lang=en', $manifest->resources['res-saws']->href);
        self::assertSame('content/tools/chisels care.html', $manifest->resources['res-chisels']->href);
        self::assertFalse($manifest->resources['res-chisels']->isSco());
        self::assertSame('https://example.org/reading', $manifest->resources['res-web']->href);
        self::assertNull($manifest->resources['res-common']->href);
        self::assertSame(['content/common/style.css'], $manifest->resources['res-common']->files);
    }

    public function testAScorm2004ManifestIsReadWithItsSequencingKept(): void
    {
        $manifest = new ScormManifestReader()->read(Packages::SCORM2004);

        self::assertSame(PackageFormat::Scorm2004, $manifest->format);
        self::assertSame('2004 4th Edition', $manifest->schemaVersion);
        self::assertSame('second', $manifest->organization()->identifier);
        self::assertCount(2, $manifest->organizations);
        self::assertStringContainsString('controlMode', (string) $manifest->organization()->sequencing);
        self::assertStringContainsString('sequencing ID="common"', (string) $manifest->sequencingCollection);

        $item = $manifest->organization()->items[0]->children[0];
        self::assertSame(0.75, $item->completionThreshold);
        self::assertStringContainsString('flow="true"', (string) $item->sequencing);
        self::assertNull($item->masteryScore);
        self::assertTrue($manifest->resources['r1']->isSco());
    }

    /**
     * @return iterable<string, array{string, PackageFormat, string}>
     */
    public static function versions(): iterable
    {
        yield '1.2' => ['<schemaversion>1.2</schemaversion>', PackageFormat::Scorm12, '1.2'];
        yield 'CAM 1.3' => ['<schemaversion>CAM 1.3</schemaversion>', PackageFormat::Scorm2004, 'CAM 1.3'];
        yield '1.3' => ['<schemaversion>1.3.1</schemaversion>', PackageFormat::Scorm2004, '1.3.1'];
        yield '2004 3rd' => ['<schemaversion>2004 3rd Edition</schemaversion>', PackageFormat::Scorm2004, '2004 3rd Edition'];
        yield 'namespace 2004' => ['<x xmlns="http://www.adlnet.org/xsd/adlcp_v1p3"/>', PackageFormat::Scorm2004, 'scorm2004'];
        yield 'namespace 1.2' => ['<x xmlns="http://www.adlnet.org/xsd/adlcp_rootv1p2"/>', PackageFormat::Scorm12, 'scorm12'];
    }

    #[DataProvider('versions')]
    public function testTheVersionIsReadFromTheSchemaVersionElseTheNamespaces(string $metadata, PackageFormat $format, string $schemaVersion): void
    {
        $manifest = new ScormManifestReader()->read(self::manifest('<metadata>'.$metadata.'</metadata>'));

        self::assertSame($format, $manifest->format);
        self::assertSame($schemaVersion, $manifest->schemaVersion);
        self::assertSame('m', $manifest->identifier);
        self::assertSame('o', $manifest->organization()->identifier);
        self::assertSame('Item', $manifest->organization()->items[0]->title);
    }

    public function testATitleDefaultsToTheIdentifierAndOddValuesAreLeftOut(): void
    {
        $manifest = new ScormManifestReader()->read(<<<'XML'
            <manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata>
              <organizations default="missing"><organization identifier="empty"/><organization identifier="o"><item identifier="i" identifierref="" isvisible="FALSE" parameters="#p"><masteryscore>120</masteryscore><completionThreshold>2</completionThreshold></item>
              <item identifier="j"><masteryscore>high</masteryscore><completionThreshold>0.5</completionThreshold></item>
              <item identifier="k"><completionThreshold>half</completionThreshold></item></organization></organizations>
              <resources><resource identifier="r" href="" scormtype="SCO"><file href=""/><dependency identifierref=""/></resource></resources>
            </manifest>
            XML);

        self::assertSame('o', $manifest->organization()->identifier);
        self::assertSame('o', $manifest->organization()->title);
        [$item, $other, $third] = $manifest->organization()->items;
        self::assertSame('i', $item->title);
        self::assertNull($item->resource);
        self::assertFalse($item->visible);
        self::assertSame('#p', $item->parameters);
        self::assertNull($item->masteryScore);
        self::assertNull($item->completionThreshold);
        self::assertNull($other->masteryScore);
        self::assertSame(0.5, $other->completionThreshold);
        self::assertNull($third->completionThreshold);
        self::assertNull($manifest->resources['r']->href);
        self::assertSame([], $manifest->resources['r']->files);
        self::assertSame([], $manifest->resources['r']->dependencies);
        self::assertTrue($manifest->resources['r']->isSco());
        self::assertSame([], new ScormManifestReader()->read(self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', ''))->resources);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wrongManifests(): iterable
    {
        yield 'not a manifest' => ['<package/>', 'The SCORM manifest has no <manifest> root.'];
        yield 'unknown version' => [self::manifest('<metadata><schemaversion>9.9</schemaversion></metadata>'), 'names no version the reader knows: "9.9"'];
        yield 'no version' => [self::manifest(''), 'names no version the reader knows: ""'];
        yield 'no organization' => ['<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><resources/></manifest>', 'describes no organization with items'];
        yield 'empty organization' => ['<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"/></organizations></manifest>', 'describes no organization with items'];
        yield 'no manifest identifier' => [str_replace('identifier="m"', '', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'An manifest of the SCORM manifest has no identifier of 1 to 255 bytes.'];
        yield 'no organization identifier' => [str_replace('identifier="o"', '', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'An organization of the SCORM manifest has no identifier'];
        yield 'no item identifier' => [str_replace('identifier="i"', '', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'An item of the SCORM manifest has no identifier'];
        yield 'long identifier' => [str_replace('identifier="i"', 'identifier="'.str_repeat('i', 256).'"', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'An item of the SCORM manifest has no identifier'];
        yield 'no resource identifier' => [str_replace('identifier="r"', '', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'An resource of the SCORM manifest has no identifier'];
        yield 'href leaving' => [str_replace('href="a.html"', 'href="../../etc/passwd"', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'holds an empty, "." or ".." segment'];
        yield 'base leaving' => [str_replace('<resources>', '<resources xml:base="../">', self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>')), 'holds an empty, "." or ".." segment'];
        yield 'entity' => ['<?xml version="1.0"?><!DOCTYPE manifest [<!ENTITY lol "lol">]><manifest/>', 'declares a document type or an entity'];
    }

    #[DataProvider('wrongManifests')]
    public function testAWrongManifestIsRefused(string $xml, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ScormManifestReader()->read($xml);
    }

    public function testAManifestIsBounded(): void
    {
        $deep = str_repeat('<item identifier="d">', 3).str_repeat('</item>', 3);
        $limits = new PackageLimits(manifestBytes: 2000, depth: 2, items: 3);

        foreach ([
            'nests its items deeper than 2 levels' => self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', '', $deep),
            'describes more than 3 items' => self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', '', str_repeat('<item identifier="x"/>', 4)),
            'describes more than 3 resources' => self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', '<resource identifier="r1"/><resource identifier="r2"/><resource identifier="r3"/><resource identifier="r4"/>'),
            'is larger than 2000 bytes' => self::manifest('<metadata><schemaversion>1.2</schemaversion><x>'.str_repeat('x', 2000).'</x></metadata>'),
        ] as $message => $xml) {
            try {
                new ScormManifestReader($limits)->read($xml);
                self::fail('The manifest is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertStringContainsString($message, $packageRefused->getMessage());
            }
        }

        self::assertCount(1, new ScormManifestReader($limits)->read(self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', '', str_repeat('<item identifier="d">', 2).str_repeat('</item>', 2)))->organization()->items);
        self::assertCount(3, new ScormManifestReader($limits)->read(self::manifest('<metadata><schemaversion>1.2</schemaversion></metadata>', '<resource identifier="r1"/><resource identifier="r2"/><resource identifier="r3"/>', '<item identifier="x"/>'))->resources);
    }

    public function testALargeSequencingIsNotKept(): void
    {
        $manifest = new ScormManifestReader()->read(self::manifest('<metadata><schemaversion>2004 4th Edition</schemaversion></metadata>', '', '<item identifier="i"><sequencing>'.str_repeat('<x/>', 20_000).'</sequencing></item>'));

        self::assertNull($manifest->organization()->items[0]->sequencing);
    }

    public function testACmi5CourseStructureIsRead(): void
    {
        $course = new Cmi5CourseReader()->read(Packages::CMI5);

        self::assertSame('https://example.org/courses/navigation', $course->id);
        self::assertSame('Navigation', $course->title);
        self::assertSame('Reading a chart.', $course->description);
        self::assertTrue($course->hasObjectives);
        self::assertCount(2, $course->children);

        $welcome = $course->children[0];
        self::assertInstanceOf(Cmi5Au::class, $welcome);
        self::assertSame('welcome/index.html?mode=short', $welcome->url);
        self::assertSame(MoveOn::NotApplicable, $welcome->moveOn);
        self::assertSame(LaunchMethod::AnyWindow, $welcome->launchMethod);
        self::assertNull($welcome->masteryScore);
        self::assertSame('', $welcome->description);

        $charts = $course->children[1];
        self::assertInstanceOf(Cmi5Block::class, $charts);
        self::assertSame('Charts', $charts->title);
        $symbols = $charts->children[0];
        self::assertInstanceOf(Cmi5Au::class, $symbols);
        self::assertSame(MoveOn::CompletedAndPassed, $symbols->moveOn);
        self::assertSame(0.8, $symbols->masteryScore);
        self::assertSame(LaunchMethod::OwnWindow, $symbols->launchMethod);
        self::assertSame('{"level":2}', $symbols->launchParameters);
        self::assertSame('key-123', $symbols->entitlementKey);
        self::assertSame('http://adlnet.gov/expapi/activities/lesson', $symbols->activityType);
        self::assertInstanceOf(Cmi5Block::class, $charts->children[1]);

        self::assertSame("Navigation à l'estime", new Cmi5CourseReader(language: 'FR')->read(Packages::CMI5)->title);
        self::assertSame('Navigation', new Cmi5CourseReader(language: 'de')->read(Packages::CMI5)->title);
    }

    public function testAStructureWithoutTitlesUsesTheIdentifiers(): void
    {
        $course = new Cmi5CourseReader()->read('<courseStructure><course id="c"><title/></course><block id="b"><title><langstring> </langstring></title><au id="a" masteryScore="1"><url>a.html</url><description><langstring>Read</langstring></description></au></block></courseStructure>');

        self::assertSame('c', $course->title);
        self::assertSame('', $course->description);
        self::assertFalse($course->hasObjectives);
        self::assertInstanceOf(Cmi5Block::class, $course->children[0]);
        self::assertSame('b', $course->children[0]->title);
        $au = $course->children[0]->children[0];
        self::assertInstanceOf(Cmi5Au::class, $au);
        self::assertSame('a', $au->title);
        self::assertSame('Read', $au->description);
        self::assertSame(1.0, $au->masteryScore);
        self::assertSame(0.0, new Cmi5CourseReader()->read('<courseStructure><course id="c"/><au id="a" masteryScore="0"><url>a</url></au></courseStructure>')->children[0]->masteryScore ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wrongStructures(): iterable
    {
        yield 'not a structure' => ['<course/>', 'has no <courseStructure> root'];
        yield 'no course' => ['<courseStructure><au id="a"><url>a</url></au></courseStructure>', 'has no <course>'];
        yield 'no course id' => ['<courseStructure><course/><au id="a"><url>a</url></au></courseStructure>', 'The course of the cmi5 course structure has no identifier'];
        yield 'spaced id' => ['<courseStructure><course id="a b"/><au id="a"><url>a</url></au></courseStructure>', 'The course of the cmi5 course structure has no identifier of 1 to 255 bytes without spaces.'];
        yield 'long id' => ['<courseStructure><course id="'.str_repeat('c', 256).'"/><au id="a"><url>a</url></au></courseStructure>', 'The course of the cmi5 course structure has no identifier'];
        yield 'no unit' => ['<courseStructure><course id="c"/><block id="b"/></courseStructure>', 'holds no assignable unit'];
        yield 'empty' => ['<courseStructure><course id="c"/></courseStructure>', 'holds no assignable unit'];
        yield 'nested blocks only' => ['<courseStructure><course id="c"/><block id="b"><block id="b2"/></block></courseStructure>', 'holds no assignable unit'];
        yield 'no unit id' => ['<courseStructure><course id="c"/><au><url>a</url></au></courseStructure>', 'An assignable unit of the cmi5 course structure has no identifier'];
        yield 'no block id' => ['<courseStructure><course id="c"/><block><au id="a"><url>a</url></au></block></courseStructure>', 'A block of the cmi5 course structure has no identifier'];
        yield 'twice' => ['<courseStructure><course id="c"/><au id="a"><url>a</url></au><block id="b"><au id="a"><url>a</url></au></block></courseStructure>', 'The identifier "a" is used twice'];
        yield 'course id reused' => ['<courseStructure><course id="c"/><au id="c"><url>a</url></au></courseStructure>', 'The identifier "c" is used twice'];
        yield 'no url' => ['<courseStructure><course id="c"/><au id="a"/></courseStructure>', 'The assignable unit "a" has no URL of 1 to 2048 bytes.'];
        yield 'empty url' => ['<courseStructure><course id="c"/><au id="a"><url> </url></au></courseStructure>', 'The assignable unit "a" has no URL'];
        yield 'long url' => ['<courseStructure><course id="c"/><au id="a"><url>'.str_repeat('u', 2049).'</url></au></courseStructure>', 'The assignable unit "a" has no URL'];
        yield 'move on' => ['<courseStructure><course id="c"/><au id="a" moveOn="Whenever"><url>a</url></au></courseStructure>', 'The moveOn "Whenever" of the assignable unit "a" is not one cmi5 defines.'];
        yield 'method' => ['<courseStructure><course id="c"/><au id="a" launchMethod="Popup"><url>a</url></au></courseStructure>', 'The launchMethod "Popup" of the assignable unit "a" is not one cmi5 defines.'];
        yield 'mastery text' => ['<courseStructure><course id="c"/><au id="a" masteryScore="high"><url>a</url></au></courseStructure>', 'The masteryScore of the assignable unit "a" is not a decimal from 0 to 1.'];
        yield 'mastery high' => ['<courseStructure><course id="c"/><au id="a" masteryScore="1.1"><url>a</url></au></courseStructure>', 'The masteryScore of the assignable unit "a" is not a decimal from 0 to 1.'];
        yield 'mastery low' => ['<courseStructure><course id="c"/><au id="a" masteryScore="-0.1"><url>a</url></au></courseStructure>', 'The masteryScore of the assignable unit "a" is not a decimal from 0 to 1.'];
    }

    #[DataProvider('wrongStructures')]
    public function testAWrongStructureIsRefused(string $xml, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Cmi5CourseReader()->read($xml);
    }

    public function testAStructureIsBounded(): void
    {
        $limits = new PackageLimits(depth: 2, items: 3);

        foreach ([
            'nests its blocks deeper than 2 levels' => '<courseStructure><course id="c"/><block id="b1"><block id="b2"><au id="a"><url>a</url></au></block></block></courseStructure>',
            'describes more than 3 blocks and units' => '<courseStructure><course id="c"/><au id="a1"><url>a</url></au><au id="a2"><url>a</url></au><au id="a3"><url>a</url></au><au id="a4"><url>a</url></au></courseStructure>',
        ] as $message => $xml) {
            try {
                new Cmi5CourseReader($limits)->read($xml);
                self::fail('The structure is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertStringContainsString($message, $packageRefused->getMessage());
            }
        }

        self::assertCount(1, new Cmi5CourseReader($limits)->read('<courseStructure><course id="c"/><block id="b1"><au id="a"><url>a</url></au><au id="a2"><url>a</url></au></block></courseStructure>')->children);
    }

    private static function manifest(string $metadata, string $resources = '<resource identifier="r" href="a.html"/>', string $items = '<item identifier="i" identifierref="r"><title>Item</title></item>'): string
    {
        return '<manifest identifier="m">'.$metadata.'<organizations><organization identifier="o"><title>Org</title>'.$items.'</organization></organizations><resources>'.$resources.'</resources></manifest>';
    }
}
