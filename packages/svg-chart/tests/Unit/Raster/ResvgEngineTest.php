<?php

declare(strict_types=1);

namespace Phil\SvgChart\Tests\Unit\Raster;

use Override;
use Phil\SvgChart\Chart;
use Phil\SvgChart\Exception\RasterizationFailed;
use Phil\SvgChart\Internal\ResvgEngine;
use Phil\SvgChart\Raster\ResvgRasterizer;
use Phil\SvgChart\SvgChartWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Resvg\Exception;
use Resvg\Renderer;

/**
 * The renderer of the `resvg` extension, where it is loaded: the size of what it draws, and what it never reads.
 */
#[CoversClass(ResvgEngine::class)]
#[CoversClass(ResvgRasterizer::class)]
#[CoversClass(RasterizationFailed::class)]
#[RequiresPhpExtension('resvg')]
final class ResvgEngineTest extends TestCase
{
    private const string BLANK = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#ffffff"/></svg>';

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-svg-chart-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        file_put_contents($this->directory.'/red.png', $this->redPng());
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->directory.'/red.png');
        rmdir($this->directory);
    }

    public function testAChartIsDrawnAtItsSizeTimesTheScale(): void
    {
        $rasterizer = new ResvgRasterizer([ResvgRasterizerTest::FONT]);
        $svg = new SvgChartWriter()->write(Chart::fromConfig(['type' => 'pie', 'data' => ['labels' => ['Été', 'Москва'], 'datasets' => [['data' => [3, 1]]]]]));

        self::assertSame([640, 400], $this->dimensions($rasterizer->png($svg)));
        self::assertSame([1280, 800], $this->dimensions($rasterizer->png($svg, 2)));
    }

    public function testAFileOutsideTheConfinedDirectoryIsNeverRead(): void
    {
        $engine = $this->engine();
        $blank = $engine->render(self::BLANK, 1);

        foreach ([$this->directory.'/red.png', 'file://'.$this->directory.'/red.png', '../../../../../../../..'.$this->directory.'/red.png', 'https://example.com/red.png'] as $href) {
            self::assertSame($blank, $engine->render($this->withImage($href), 1), $href);
        }

        // The same image, unconfined, is drawn: the test above would see it.
        self::assertNotSame($blank, new Renderer()->render($this->withImage($this->directory.'/red.png')));
    }

    public function testWhatTheRendererRefusesFails(): void
    {
        $engine = $this->engine();

        foreach ([
            'measure' => static fn (): mixed => $engine->measure('not an image', 1),
            'render' => static fn (): mixed => $engine->render('not an image', 1),
            'too many pixels' => static fn (): mixed => $engine->render('<svg xmlns="http://www.w3.org/2000/svg" width="10000" height="10000"/>', 1),
            'too many elements' => static fn (): mixed => $engine->render('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1">'.str_repeat('<g/>', 1_000_001).'</svg>', 1),
        ] as $case => $call) {
            try {
                $call();
                self::fail($case);
            } catch (RasterizationFailed $rasterizationFailed) {
                self::assertStringStartsWith('The image cannot be drawn as a PNG: the renderer refused it, ', $rasterizationFailed->getMessage(), $case);
                self::assertInstanceOf(Exception::class, $rasterizationFailed->getPrevious(), $case);
            }
        }
    }

    public function testAHugeImageIsRefusedByTheRasterizerFirst(): void
    {
        $rasterizer = new ResvgRasterizer([ResvgRasterizerTest::FONT]);

        foreach ([
            'The image cannot be drawn as a PNG: it would have 5000 × 5000 pixels, more than the 16777216 allowed.' => '<svg xmlns="http://www.w3.org/2000/svg" width="5000" height="5000"/>',
            'The image cannot be drawn as a PNG: it has 2000046 bytes, more than the 1048576 allowed.' => '<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat('<g/>', 500_000).'</svg>',
        ] as $message => $svg) {
            try {
                $rasterizer->png($svg);
                self::fail($message);
            } catch (RasterizationFailed $rasterizationFailed) {
                self::assertSame($message, $rasterizationFailed->getMessage());
            }
        }
    }

    private function engine(): ResvgEngine
    {
        return new ResvgEngine([ResvgRasterizerTest::FONT], 'Test', ResvgRasterizer::CONFINED_DIRECTORY);
    }

    private function withImage(string $href): string
    {
        return str_replace('</svg>', \sprintf('<image href="%s" width="10" height="10"/></svg>', htmlspecialchars($href, \ENT_QUOTES | \ENT_XML1)), self::BLANK);
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        /** @var array{width: int, height: int} $header */
        $header = unpack('Nwidth/Nheight', $png, 16);

        return [$header['width'], $header['height']];
    }

    /**
     * A 10 × 10 red PNG, written here.
     */
    private function redPng(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\xff\x00\x00", 10), 10);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 10, 10, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress($rows)).$chunk('IEND', '');
    }
}
