<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Exception\InvalidLimits;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Xml\XmlDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The budgets of a manifest counted before a tree of it is built: its XML nodes — elements, attributes, text and
 * comments — and the depth of its elements. A manifest of the budget is read, one node or one level more is refused.
 */
#[CoversClass(PackageLimits::class)]
#[CoversClass(XmlDocument::class)]
#[CoversClass(ScormManifestReader::class)]
#[CoversClass(Cmi5CourseReader::class)]
final class ManifestBudgetTest extends TestCase
{
    private const string MANIFEST = '<manifest identifier="m"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o"><item identifier="i"><title>I</title></item></organization></organizations></manifest>';

    public function testTheDefaultsReadEveryRealManifest(): void
    {
        $limits = new PackageLimits();

        self::assertSame([500_000, 64], [$limits->manifestNodes, $limits->manifestDepth]);
        self::assertSame([500_000, 64], [PackageLimits::MANIFEST_NODES, PackageLimits::MANIFEST_DEPTH]);
        self::assertSame([1, 1], [new PackageLimits(manifestNodes: 1)->manifestNodes, new PackageLimits(manifestDepth: 1)->manifestDepth]);
    }

    public function testABudgetIsAtLeastOne(): void
    {
        foreach ([['manifestNodes' => 0], ['manifestDepth' => 0]] as $arguments) {
            try {
                new PackageLimits(...$arguments);
                self::fail('A budget of 0 is accepted.');
            } catch (InvalidLimits $invalidLimits) {
                self::assertSame('Every limit of a package is a positive number.', $invalidLimits->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function nodes(): iterable
    {
        yield 'elements' => ['<a><b/><c/></a>', 3];
        yield 'attributes' => ['<a x="1" y="2"/>', 3];
        yield 'text' => ['<a>t<b>u</b></a>', 4];
        yield 'comment' => ['<a><!-- c --></a>', 2];
        yield 'processing instruction' => ['<a><?p x?></a>', 2];
        yield 'end tags' => ['<a><b></b></a>', 2];
    }

    #[DataProvider('nodes')]
    public function testTheNodesOfTheBudgetAreReadAndOneMoreIsRefused(string $xml, int $nodes): void
    {
        self::assertSame('a', XmlDocument::root($xml, new PackageLimits(manifestNodes: $nodes), 'The test')->localName);

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The test holds more than %d XML nodes.', $nodes - 1));

        XmlDocument::root($xml, new PackageLimits(manifestNodes: $nodes - 1), 'The test');
    }

    public function testTheDepthOfTheBudgetIsReadAndOneLevelMoreIsRefused(): void
    {
        $xml = '<a>text<b x="1"><c/></b></a>';

        self::assertSame('a', XmlDocument::root($xml, new PackageLimits(manifestDepth: 3), 'The test')->localName);
        self::assertSame('a', XmlDocument::root('<a>text</a>', new PackageLimits(manifestDepth: 1), 'The test')->localName);

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The test nests its elements deeper than 2 levels.');

        XmlDocument::root($xml, new PackageLimits(manifestDepth: 2), 'The test');
    }

    public function testAMalformedDocumentIsRefusedAfterItsCount(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The test is not well-formed XML.');

        XmlDocument::root('<a><b/><c>', new PackageLimits(manifestNodes: 3), 'The test');
    }

    public function testBothReadersCountBeforeTheyLoad(): void
    {
        self::assertSame('m', new ScormManifestReader(new PackageLimits(manifestNodes: 12))->read(self::MANIFEST)->identifier);

        try {
            new ScormManifestReader(new PackageLimits(manifestNodes: 11))->read(self::MANIFEST);
            self::fail('A manifest past its budget is read.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The SCORM manifest holds more than 11 XML nodes.', $packageRefused->getMessage());
        }

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The cmi5 course structure nests its elements deeper than 1 levels.');

        new Cmi5CourseReader(new PackageLimits(manifestDepth: 1))->read('<courseStructure><course id="https://example.org/c"/></courseStructure>');
    }

    public function testAHostileManifestUnderTheByteLimitIsRefusedBeforeItIsLoaded(): void
    {
        $limits = new PackageLimits();
        $flat = '<manifest>'.str_repeat('<a/>', 600_000).'</manifest>';
        $deep = str_repeat('<a>', 1_000).str_repeat('</a>', 1_000);

        self::assertLessThan($limits->manifestBytes, \strlen($flat));

        foreach ([[$flat, 'holds more than 500000 XML nodes'], [$deep, 'nests its elements deeper than 64 levels']] as [$xml, $message]) {
            try {
                new ScormManifestReader($limits)->read($xml);
                self::fail('A hostile manifest is read.');
            } catch (PackageRefused $packageRefused) {
                self::assertStringContainsString($message, $packageRefused->getMessage());
            }
        }
    }

    public function testTheErrorsOfLibxmlAreGivenBackWhetherTheManifestIsRefusedOrNot(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            foreach (['<a/>', '<a><b/></a>', '<a>'] as $xml) {
                try {
                    XmlDocument::root($xml, new PackageLimits(manifestNodes: 1), 'The test');
                } catch (PackageRefused) {
                    // Refused for its budget or for its form: libxml is left as it was found either way.
                }

                self::assertFalse(libxml_use_internal_errors(false));
                self::assertSame([], libxml_get_errors());
            }
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
