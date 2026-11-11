<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Benchmark;

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\TextFitter;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\Zone;

/**
 * A print file is composed each time a customer previews a design: a zone of 100 × 100 mm, an image under a fitted
 * text, at 72, 150 and 300 dpi; the time grows with the pixels, four times from 150 to 300 dpi. Fitting a text alone
 * is the search for the largest size whose lines fit the box, which a preview runs at each keystroke.
 *
 * @BeforeMethods("setUp")
 */
final class PrintCompositorBench
{
    private readonly PrintCompositor $compositor;

    private readonly TextFitter $fitter;

    private readonly TextItem $text;

    /** @var list<Layer> */
    private readonly array $layers;

    private PrintJob $job;

    /** The file composed, kept so that writing it is not dropped as unused. */
    public string $png = '';

    public function __construct()
    {
        $this->compositor = new PrintCompositor();
        $this->fitter = new TextFitter();
        $this->text = new TextItem(
            str_repeat('Rest in pieces, the quick brown fox. ', 4),
            TypeFace::fromFile(Fonts::trueType()),
            new Box(10, 60, 80, 30),
            Colour::fromHex('#1a1a1a'),
            maxSize: 48,
            minSize: 6,
        );
        $this->layers = [
            new Layer('artwork', [new ImageItem(Raster::blank(400, 400, Colour::fromHex('#ff8000')), new Box(0, 0, 100, 60))]),
            new Layer('text', [$this->text]),
        ];
        $this->job = new PrintJob(new Zone('front', 100, 100), $this->layers);
    }

    /**
     * @param array{dpi: int} $params
     */
    public function setUp(array $params): void
    {
        $this->job = new PrintJob(new Zone('front', 100, 100), $this->layers, $params['dpi']);
    }

    /**
     * @ParamProviders("resolutions")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchCompose(): void
    {
        $this->png = $this->compositor->compose($this->job)->png();
    }

    /**
     * @ParamProviders("resolutions")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     *
     * @param array{dpi: int} $params
     */
    public function benchFitText(array $params): void
    {
        $this->fitter->fit($this->text, $params['dpi']);
    }

    /**
     * @return iterable<string, array{dpi: int}>
     */
    public function resolutions(): iterable
    {
        yield '72 dpi' => ['dpi' => 72];
        yield '150 dpi' => ['dpi' => 150];
        yield '300 dpi' => ['dpi' => 300];
    }
}
