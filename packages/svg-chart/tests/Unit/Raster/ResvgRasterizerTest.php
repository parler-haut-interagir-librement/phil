<?php

declare(strict_types=1);

namespace Phil\SvgChart\Tests\Unit\Raster;

use Phil\Fonts\Exception\InvalidFont;
use Phil\SvgChart\Chart;
use Phil\SvgChart\Exception\InvalidRasterizer;
use Phil\SvgChart\Exception\RasterizationFailed;
use Phil\SvgChart\Exception\RasterizerUnavailable;
use Phil\SvgChart\Internal\ResvgEngine;
use Phil\SvgChart\Raster\ResvgRasterizer;
use Phil\SvgChart\SvgChartWriter;
use Phil\SvgChart\Tests\Unit\Fixture\RecordingEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the rasterizer checks before the renderer draws anything, with a renderer that draws nothing: these tests run
 * with or without the `resvg` extension.
 */
#[CoversClass(ResvgRasterizer::class)]
#[CoversClass(InvalidRasterizer::class)]
#[CoversClass(RasterizationFailed::class)]
#[CoversClass(RasterizerUnavailable::class)]
#[CoversClass(ResvgEngine::class)]
final class ResvgRasterizerTest extends TestCase
{
    public const string FONT = __DIR__.'/../Fixture/fonts/test.ttf';

    public function testAChartIsSanitizedMeasuredThenDrawn(): void
    {
        $engine = new RecordingEngine();
        $svg = new SvgChartWriter()->write(Chart::fromConfig(['type' => 'bar', 'data' => ['labels' => ['Été'], 'datasets' => [['label' => 'Ventes', 'data' => [3]]]]]));

        self::assertSame("\x89PNG", new ResvgRasterizer([self::FONT], engine: $engine)->png($svg, 2));
        self::assertCount(1, $engine->measured);
        self::assertSame($engine->measured, $engine->rendered);
        self::assertSame(2, $engine->rendered[0][1]);
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" font-family="system-ui, sans-serif" font-size="12">', $engine->rendered[0][0]);
        self::assertStringContainsString('<rect x="84.4" y="12" width="483.2" height="340"><title>Ventes, Été: 3</title></rect>', $engine->rendered[0][0]);
    }

    public function testTheScaleIsOneByDefault(): void
    {
        $engine = new RecordingEngine();
        new ResvgRasterizer([self::FONT], engine: $engine)->png('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        self::assertSame(1, $engine->rendered[0][1]);
    }

    public function testWhatCouldReadAFileOrRunIsRemovedBeforeTheRendererSeesIt(): void
    {
        $engine = new RecordingEngine();
        $png = base64_encode((string) hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010806000000'));
        new ResvgRasterizer([self::FONT], engine: $engine)->png(<<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="10" height="10" onload="alert(1)">
              <script>alert(2)</script>
              <image href="/etc/passwd" width="10" height="10"/>
              <image xlink:href="https://example.com/remote.png" width="10" height="10"/>
              <image href="../secret.png" width="10" height="10"/>
              <image href="data:image/png;base64,{$png}" width="10" height="10"/>
              <use href="https://example.com/sprite.svg#icon"/>
              <rect width="10" height="10" fill="#ff0000"/>
            </svg>
            SVG);

        $drawn = $engine->rendered[0][0];

        foreach (['script', 'alert', 'onload', '/etc/passwd', 'example.com', 'secret', 'data:'] as $removed) {
            self::assertStringNotContainsString($removed, $drawn);
        }

        self::assertStringContainsString('<rect width="10" height="10" fill="#ff0000"/>', $drawn);
    }

    public function testAnImageTooLargeInBytesIsRefusedBeforeAnythingElse(): void
    {
        $engine = new RecordingEngine();
        $rasterizer = new ResvgRasterizer([self::FONT], maxBytes: 100, engine: $engine);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'.str_repeat(' ', 100 - 69).'</svg>';

        self::assertSame(100, \strlen($svg));
        self::assertSame("\x89PNG", $rasterizer->png($svg));

        $this->assertRefused('The image cannot be drawn as a PNG: it has 101 bytes, more than the 100 allowed.', static fn (): string => $rasterizer->png($svg.' '));
        self::assertCount(1, $engine->measured);
    }

    public function testAnImageTheSanitizerRefusesIsNeverMeasured(): void
    {
        $engine = new RecordingEngine();
        $rasterizer = new ResvgRasterizer([self::FONT], engine: $engine);

        $this->assertRefused('The image cannot be drawn as a PNG: the sanitizer refused it, entity_declaration.', static fn (): string => $rasterizer->png('<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg">&a;</svg>'));
        $this->assertRefused('The image cannot be drawn as a PNG: the sanitizer refused it, not_svg.', static fn (): string => $rasterizer->png('<html/>'));
        self::assertSame([], $engine->measured);
    }

    public function testAnImageTooLargeInPixelsIsRefusedBeforeItIsDrawn(): void
    {
        $engine = new RecordingEngine(100, 50);
        $rasterizer = new ResvgRasterizer([self::FONT], maxPixels: 20_000, engine: $engine);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"/>';

        self::assertSame("\x89PNG", $rasterizer->png($svg, 2));
        $this->assertRefused('The image cannot be drawn as a PNG: it would have 300 × 150 pixels, more than the 20000 allowed.', static fn (): string => $rasterizer->png($svg, 3));
        self::assertCount(2, $engine->measured);
        self::assertCount(1, $engine->rendered);
    }

    public function testTheScaleIsFromOneToFour(): void
    {
        $engine = new RecordingEngine(1, 1);
        $rasterizer = new ResvgRasterizer([self::FONT], engine: $engine);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';

        self::assertSame("\x89PNG", $rasterizer->png($svg, 4));

        foreach ([0, 5] as $scale) {
            $this->assertRefused(\sprintf('The image cannot be drawn as a PNG: a scale of 1 to 4 is expected, %d given.', $scale), static fn (): string => $rasterizer->png($svg, $scale));
        }

        self::assertCount(1, $engine->measured);
    }

    public function testTheDefaultLimitsAreOneMebibyteAndSixteenMegapixels(): void
    {
        $head = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1">';
        $svg = $head.str_repeat(' ', 1_048_576 - \strlen($head) - 6).'</svg>';
        $rasterizer = new ResvgRasterizer([self::FONT], engine: new RecordingEngine());

        self::assertSame(1_048_576, \strlen($svg));
        self::assertSame("\x89PNG", $rasterizer->png($svg));
        $this->assertRefused('The image cannot be drawn as a PNG: it has 1048577 bytes, more than the 1048576 allowed.', static fn (): string => $rasterizer->png($svg.' '));

        $small = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
        self::assertSame("\x89PNG", new ResvgRasterizer([self::FONT], engine: new RecordingEngine(4096, 4096))->png($small));
        $this->assertRefused('The image cannot be drawn as a PNG: it would have 4097 × 4096 pixels, more than the 16777216 allowed.', static fn (): string => new ResvgRasterizer([self::FONT], engine: new RecordingEngine(4097, 4096))->png($small));
        self::assertInstanceOf(ResvgRasterizer::class, new ResvgRasterizer([self::FONT], maxBytes: 1, engine: new RecordingEngine()));
    }

    public function testTheLimitsAreInRange(): void
    {
        $engine = new RecordingEngine();

        foreach ([
            'The rasterizer cannot be built: a limit of 1 to 16777216 bytes is expected, 0 given.' => static fn (): ResvgRasterizer => new ResvgRasterizer([self::FONT], maxBytes: 0, engine: $engine),
            'The rasterizer cannot be built: a limit of 1 to 16777216 bytes is expected, 16777217 given.' => static fn (): ResvgRasterizer => new ResvgRasterizer([self::FONT], maxBytes: 16_777_217, engine: $engine),
            'The rasterizer cannot be built: a limit of 1 to 67108864 pixels is expected, 0 given.' => static fn (): ResvgRasterizer => new ResvgRasterizer([self::FONT], maxPixels: 0, engine: $engine),
            'The rasterizer cannot be built: a limit of 1 to 67108864 pixels is expected, 67108865 given.' => static fn (): ResvgRasterizer => new ResvgRasterizer([self::FONT], maxPixels: 67_108_865, engine: $engine),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidRasterizer $invalidRasterizer) {
                self::assertSame($message, $invalidRasterizer->getMessage());
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
        $smallest = new ResvgRasterizer([self::FONT], \strlen($svg), 1, new RecordingEngine(1, 1));
        self::assertSame("\x89PNG", $smallest->png($svg));
        self::assertSame("\x89PNG", new ResvgRasterizer([self::FONT], 16_777_216, 67_108_864, new RecordingEngine(8192, 8192))->png($svg));
    }

    public function testTheFontsAreReadAndMustBeTrueTypeOrOpenType(): void
    {
        $engine = new RecordingEngine();
        $missing = __DIR__.'/missing.ttf';
        $woff = __DIR__.'/../Fixture/fonts/test.woff';

        try {
            new ResvgRasterizer([self::FONT, $missing], engine: $engine);
            self::fail('Built.');
        } catch (InvalidRasterizer $invalidRasterizer) {
            self::assertSame(\sprintf('The rasterizer cannot be built: the font file "%s" cannot be read.', $missing), $invalidRasterizer->getMessage());
            self::assertInstanceOf(InvalidFont::class, $invalidRasterizer->getPrevious());
        }

        try {
            new ResvgRasterizer([$woff], engine: $engine);
            self::fail('Built.');
        } catch (InvalidRasterizer $invalidRasterizer) {
            self::assertSame(\sprintf('The rasterizer cannot be built: the font file "%s" is WOFF, and the renderer reads TrueType and OpenType files.', $woff), $invalidRasterizer->getMessage());
        }

        try {
            // @phpstan-ignore argument.type (no font, which the rasterizer refuses)
            new ResvgRasterizer([], engine: $engine);
            self::fail('Built.');
        } catch (InvalidRasterizer $invalidRasterizer) {
            self::assertSame('The rasterizer cannot be built: at least one font file is expected.', $invalidRasterizer->getMessage());
        }
    }

    public function testWithoutTheExtensionThePngIsNotOffered(): void
    {
        self::assertSame(\extension_loaded('resvg'), ResvgRasterizer::isAvailable());
        self::assertSame('resvg', ResvgRasterizer::EXTENSION);

        if (ResvgRasterizer::isAvailable()) {
            self::markTestSkipped('The resvg extension is loaded.');
        }

        try {
            new ResvgRasterizer([self::FONT]);
            self::fail('Built.');
        } catch (RasterizerUnavailable $rasterizerUnavailable) {
            self::assertSame('The PHP extension "resvg" is not loaded: charts are drawn as SVG only.', $rasterizerUnavailable->getMessage());
        }
    }

    /**
     * @param callable(): string $draw
     */
    private function assertRefused(string $message, callable $draw): void
    {
        try {
            $draw();
            self::fail($message);
        } catch (RasterizationFailed $rasterizationFailed) {
            self::assertSame($message, $rasterizationFailed->getMessage());
        }
    }
}
