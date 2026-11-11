<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\Fonts\EmbeddingPermissions;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Check\Colours;
use Phil\PrintPreflight\Check\Fonts;
use Phil\PrintPreflight\Check\Hairlines;
use Phil\PrintPreflight\Check\ImageResolution;
use Phil\PrintPreflight\Check\InkCoverage;
use Phil\PrintPreflight\Check\Overprint;
use Phil\PrintPreflight\Check\PageCount;
use Phil\PrintPreflight\Check\PageGeometry;
use Phil\PrintPreflight\Check\Placement;
use Phil\PrintPreflight\Check\Transparency;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preflight::class)]
final class PreflightTest extends TestCase
{
    public function testTheBuiltInChecksRunInOrder(): void
    {
        $preflight = new Preflight();

        self::assertSame([PageCount::class, PageGeometry::class, Fonts::class, ImageResolution::class, Colours::class, InkCoverage::class, Overprint::class, Transparency::class, Hairlines::class, Placement::class], array_map(static fn (Check $check): string => $check::class, $preflight->checks));
        self::assertSame(1, $preflight->profile->minimumPages);
        self::assertEquals(Preflight::defaultChecks(), $preflight->checks);
    }

    public function testAPrintReadyDocumentPasses(): void
    {
        $page = new Page(Size::a5(), Length::mm(3), [
            new PlacedImage('cover', Rectangle::mm(-3, -3, 154, 216), 1890, 2650, colourSpace: ColourSpace::Cmyk, bleeds: true),
        ], [new Box('title', Rectangle::mm(15, 20, 118, 30))]);
        $report = new Preflight(new PrintProfile(trimSizes: [Size::a5()], pageMultiple: 2))->check(new Document([$page, $page], [new UsedFont('Sans', subset: true)]));

        self::assertTrue($report->isClean());
        self::assertCount(0, $report);
    }

    public function testFindingsAreReportedForTheDocumentFirstThenPageByPage(): void
    {
        $low = new PlacedImage('low', Rectangle::in(1, 1, 2, 2), 200, 200);
        $document = new Document([
            new Page(Size::a4(), Length::mm(3), [$low]),
            new Page(Size::a4(), Length::mm(3), [$low]),
            new Page(Size::a4(), Length::mm(1)),
        ], [new UsedFont('Locked', new EmbeddingPermissions(0x0002))]);
        $report = new Preflight(new PrintProfile(pageMultiple: 4))->check($document);

        self::assertSame([
            [null, 'page-count.multiple'],
            [null, 'font.restricted'],
            [1, 'image.below-minimum'],
            [1, 'colour.rgb-in-cmyk'],
            [2, 'image.below-minimum'],
            [2, 'colour.rgb-in-cmyk'],
            [3, 'bleed.too-small'],
        ], array_map(static fn (Finding $finding): array => [$finding->page, $finding->code], $report->findings));
        self::assertFalse($report->passes());
    }

    public function testTheProfileSetsSeveritiesAndIgnoresRules(): void
    {
        $profile = new PrintProfile(pageMultiple: 4, severities: [Rule::PageCountMultiple->value => Severity::Warning], ignore: [Rule::ColourRgbInCmyk->value]);
        $document = new Document([new Page(Size::a4(), Length::mm(3), [new PlacedImage('photo', Rectangle::in(1, 1, 1, 1), 300, 300)])]);
        $report = new Preflight($profile)->check($document);

        self::assertSame([['warning', 'page-count.multiple', 'The document has 1 page, not a multiple of 4; add 3 blank pages or remove 1.', null]], PageChecksTest::describe($report->findings));
        self::assertTrue($report->passes());
    }

    public function testAnApplicationAddsItsOwnChecks(): void
    {
        $check = new class implements Check {
            public function check(Document $document, PrintProfile $profile): array
            {
                return [
                    new Finding(Severity::Warning, 'app.spine', 'The spine text is too long.', 1),
                    new Finding(Severity::Notice, 'app.paper', 'Printed on cream paper.'),
                ];
            }
        };
        $preflight = new Preflight(new PrintProfile(bleed: Length::pt(0), severities: ['app.paper' => Severity::Error]));
        $extended = $preflight->withCheck($check);
        $report = $extended->check(new Document([new Page(Size::a4())]));

        self::assertCount(10, $preflight->checks);
        self::assertCount(11, $extended->checks);
        self::assertSame($check, $extended->checks[10]);
        self::assertSame($preflight->profile, $extended->profile);
        self::assertSame([['error', 'app.paper', 'Printed on cream paper.', null], ['warning', 'app.spine', 'The spine text is too long.', 1]], PageChecksTest::describe($report->findings));
        self::assertSame([], new Preflight(checks: [])->check(new Document([]))->findings);
    }
}
