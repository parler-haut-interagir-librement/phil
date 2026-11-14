<?php

declare(strict_types=1);

namespace Phil\SvgChart\Tests\Unit\Fixture;

use Override;
use Phil\SvgChart\Internal\Contract\RasterEngine;

/**
 * A renderer that draws nothing: it gives the size it was told, a PNG signature, and keeps what it was asked.
 */
final class RecordingEngine implements RasterEngine
{
    /** @var list<array{string, int}> */
    public array $measured = [];

    /** @var list<array{string, int}> */
    public array $rendered = [];

    public function __construct(
        private readonly int $width = 640,
        private readonly int $height = 400,
    ) {
    }

    #[Override]
    public function measure(string $svg, int $scale): array
    {
        $this->measured[] = [$svg, $scale];

        return ['width' => $this->width * $scale, 'height' => $this->height * $scale];
    }

    #[Override]
    public function render(string $svg, int $scale): string
    {
        $this->rendered[] = [$svg, $scale];

        return "\x89PNG";
    }
}
