<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Draws pages as PNG or JPEG images: thumbnails, previews. It comes last; after other stages, the ZIP holds the PDF
 * next to its images (`report_1.png`).
 */
final readonly class Render implements Stage
{
    public ?Pages $pages;

    /**
     * @param int|null $dpi     the resolution, at least 1; 150 when unset
     * @param int|null $quality JPEG only, from 1 to 100
     */
    public function __construct(
        Pages|string|null $pages = null,
        public ?int $dpi = null,
        public ?ImageFormat $format = null,
        public ?int $quality = null,
    ) {
        Check::above('A resolution', $dpi, 0);
        Check::between('A JPEG quality', $quality, 1, 100);

        if (null !== $quality && ImageFormat::Jpeg !== $format) {
            throw InvalidStage::because('A quality goes with the JPEG format only.');
        }

        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Render;
    }

    #[Override]
    public function options(): array
    {
        return Check::set(['pages' => $this->pages?->value, 'dpi' => $this->dpi, 'format' => $this->format?->value, 'quality' => $this->quality]);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
