<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;

/**
 * The image processor, each rendering timed and noted in the trace; registered in debug only.
 */
final readonly class TraceableImageProcessor implements ImageProcessor
{
    public function __construct(
        private ImageProcessor $images,
        private MediaTrace $trace,
    ) {
    }

    #[Override]
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
    {
        $start = hrtime(true);
        $rendered = $this->images->render($image, $format, $focalPoint, $type);
        $this->trace->rendered($format->name, $type, \strlen($rendered), (hrtime(true) - $start) / 1e6);

        return $rendered;
    }
}
