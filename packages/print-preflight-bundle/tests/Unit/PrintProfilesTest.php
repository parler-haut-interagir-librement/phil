<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\UnknownProfile;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintProfiles::class)]
#[CoversClass(UnknownProfile::class)]
final class PrintProfilesTest extends TestCase
{
    public function testBuildsTheProfilesOfTheConfiguration(): void
    {
        $profiles = PrintProfiles::fromConfiguration([
            'poster' => self::profile(trim_sizes: ['a4', '297x210mm']),
            'booklet' => self::profile(
                trim_sizes: ['a5'],
                uniform_trim_size: false,
                bleed: '0.125in',
                safety_margin: '6mm',
                minimum_resolution: 200.0,
                target_resolution: 350.0,
                maximum_resolution: 900.0,
                minimum_pages: 8,
                maximum_pages: 64,
                page_multiple: 4,
                colour_workflow: 'rgb',
                allow_spot_colours: true,
                severities: ['page-count.multiple' => 'warning'],
                ignore: ['colour.spot'],
                maximum_ink_coverage: 260.0,
                minimum_line_width: '0.1mm',
                allow_overprint: false,
                allow_transparency: false,
            ),
        ], 'booklet');
        $booklet = $profiles->get();

        self::assertSame(['poster', 'booklet'], $profiles->names());
        self::assertSame('booklet', $profiles->defaultName());
        self::assertSame($booklet, $profiles->get('booklet'));
        self::assertCount(1, $booklet->trimSizes);
        self::assertTrue($booklet->trimSizes[0]->matches(Size::a5()));
        self::assertFalse($booklet->uniformTrimSize);
        self::assertSame(9.0, $booklet->bleed->points);
        self::assertEqualsWithDelta(6.0, $booklet->safetyMargin->millimetres(), 0.0001);
        self::assertSame(200.0, $booklet->minimumResolution);
        self::assertSame(350.0, $booklet->targetResolution);
        self::assertSame(900.0, $booklet->maximumResolution);
        self::assertSame(8, $booklet->minimumPages);
        self::assertSame(64, $booklet->maximumPages);
        self::assertSame(4, $booklet->pageMultiple);
        self::assertSame(ColourWorkflow::Rgb, $booklet->colourWorkflow);
        self::assertTrue($booklet->allowSpotColours);
        self::assertSame(['page-count.multiple' => Severity::Warning], $booklet->severities);
        self::assertSame(['colour.spot'], $booklet->ignore);
        self::assertSame(260.0, $booklet->maximumInkCoverage);
        self::assertEqualsWithDelta(0.1, $booklet->minimumLineWidth->millimetres(), 0.0001);
        self::assertFalse($booklet->allowOverprint);
        self::assertFalse($booklet->allowTransparency);
        self::assertSame(0.25, $profiles->get('poster')->minimumLineWidth->points);
        self::assertTrue($profiles->get('poster')->allowOverprint);
        self::assertTrue($profiles->get('poster')->allowTransparency);
        self::assertCount(2, $profiles->get('poster')->trimSizes);
    }

    public function testTheFirstProfileIsTheDefault(): void
    {
        $profiles = PrintProfiles::fromConfiguration(['poster' => self::profile(), 'booklet' => self::profile()]);

        self::assertSame('poster', $profiles->defaultName());
        self::assertSame($profiles->get('poster'), $profiles->get());
    }

    public function testWithoutProfileTheLibraryDefaults(): void
    {
        $profiles = PrintProfiles::fromConfiguration([]);

        self::assertSame(['default'], $profiles->names());
        self::assertEquals(new PrintProfile(), $profiles->get());
    }

    public function testANumericNameIsAName(): void
    {
        $profiles = new PrintProfiles(['2026' => new PrintProfile()], '2026');

        self::assertSame(['2026'], $profiles->names());

        $this->expectExceptionMessageIsOrContains('There is no printer profile "2027"; the profiles are "2026".');

        new PrintProfiles(['2026' => new PrintProfile()], '2027');
    }

    public function testRefusesAnUnknownName(): void
    {
        $profiles = new PrintProfiles(['poster' => new PrintProfile(), 'booklet' => new PrintProfile()], 'poster');

        $this->expectException(UnknownProfile::class);
        $this->expectExceptionMessageIsOrContains('There is no printer profile "zine"; the profiles are "poster", "booklet".');

        $profiles->get('zine');
    }

    public function testRefusesAnUnknownDefault(): void
    {
        $this->expectException(UnknownProfile::class);
        $this->expectExceptionMessageIsOrContains('There is no printer profile "zine"; the profiles are "default".');

        PrintProfiles::fromConfiguration([], 'zine');
    }

    /**
     * @param list<string>          $trim_sizes
     * @param array<string, string> $severities
     * @param list<string>          $ignore
     *
     * @return array{trim_sizes: list<string>, uniform_trim_size: bool, bleed: string, safety_margin: string, minimum_resolution: float, target_resolution: float, maximum_resolution: ?float, minimum_pages: int, maximum_pages: ?int, page_multiple: int, colour_workflow: string, allow_spot_colours: bool, severities: array<string, string>, ignore: list<string>, maximum_ink_coverage: float, minimum_line_width: string, allow_overprint: bool, allow_transparency: bool}
     */
    public static function profile(
        array $trim_sizes = [],
        bool $uniform_trim_size = true,
        string $bleed = '3mm',
        string $safety_margin = '5mm',
        float $minimum_resolution = 150.0,
        float $target_resolution = 300.0,
        ?float $maximum_resolution = null,
        int $minimum_pages = 1,
        ?int $maximum_pages = null,
        int $page_multiple = 1,
        string $colour_workflow = 'cmyk',
        bool $allow_spot_colours = false,
        array $severities = [],
        array $ignore = [],
        float $maximum_ink_coverage = 300.0,
        string $minimum_line_width = '0.25pt',
        bool $allow_overprint = true,
        bool $allow_transparency = true,
    ): array {
        return [
            'trim_sizes' => $trim_sizes,
            'uniform_trim_size' => $uniform_trim_size,
            'bleed' => $bleed,
            'safety_margin' => $safety_margin,
            'minimum_resolution' => $minimum_resolution,
            'target_resolution' => $target_resolution,
            'maximum_resolution' => $maximum_resolution,
            'minimum_pages' => $minimum_pages,
            'maximum_pages' => $maximum_pages,
            'page_multiple' => $page_multiple,
            'colour_workflow' => $colour_workflow,
            'allow_spot_colours' => $allow_spot_colours,
            'severities' => $severities,
            'ignore' => $ignore,
            'maximum_ink_coverage' => $maximum_ink_coverage,
            'minimum_line_width' => $minimum_line_width,
            'allow_overprint' => $allow_overprint,
            'allow_transparency' => $allow_transparency,
        ];
    }
}
