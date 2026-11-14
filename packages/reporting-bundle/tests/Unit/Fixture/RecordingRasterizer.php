<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\SvgChart\Contract\Rasterizer;
use Phil\SvgChart\Exception\RasterizationFailed;

/**
 * A rasterizer that draws nothing: it keeps the SVG and the scale it was asked, and gives them back as its "PNG", or
 * refuses every image.
 */
final class RecordingRasterizer implements Rasterizer
{
    /** @var list<array{string, int}> */
    public array $drawn = [];

    public function __construct(private readonly bool $refuses = false)
    {
    }

    #[Override]
    public function png(string $svg, int $scale = 1): string
    {
        if ($this->refuses) {
            throw RasterizationFailed::because('refused by the test');
        }

        $this->drawn[] = [$svg, $scale];

        return 'PNG '.$scale;
    }
}
