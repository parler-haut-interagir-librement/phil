<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Draws a page of another PDF over pages: a letterhead, a seal, a watermark drawn on a transparent page.
 */
final readonly class StampPdf implements Stage
{
    public ?Pages $pages;

    public function __construct(
        public InputFile $source,
        public ?int $sourcePage = null,
        public float $x = 0,
        public float $y = 0,
        public ?float $scale = null,
        public ?float $opacity = null,
        public ?float $rotation = null,
        Pages|string|null $pages = null,
    ) {
        Check::extension('A stamped PDF', $source, ['pdf']);

        if (null !== $sourcePage && $sourcePage < 1) {
            throw InvalidStage::because(\sprintf('The page of a stamped PDF counts from 1; %d does not.', $sourcePage));
        }

        Check::above('The scale of a stamp', $scale, 0);
        Check::between('An opacity', $opacity, 0, 1);
        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::StampPdf;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'source' => $this->source->name,
            'sourcePage' => $this->sourcePage,
            'pages' => $this->pages?->value,
            'x' => $this->x,
            'y' => $this->y,
            'scale' => $this->scale,
            'opacity' => $this->opacity,
            'rotation' => $this->rotation,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [$this->source];
    }
}
