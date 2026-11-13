<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Draws a PNG or JPEG image over pages: a logo, a signature, a seal. A width or a height keeps the image's proportions;
 * neither draws each pixel as a point.
 */
final readonly class StampImage implements Stage
{
    public ?Pages $pages;

    public function __construct(
        public InputFile $image,
        public float $x = 0,
        public float $y = 0,
        public ?float $width = null,
        public ?float $height = null,
        public ?float $opacity = null,
        public ?float $rotation = null,
        Pages|string|null $pages = null,
    ) {
        Check::extension('A stamped image', $image, ['png', 'jpg', 'jpeg']);
        Check::above('The width of a stamp', $width, 0);
        Check::above('The height of a stamp', $height, 0);
        Check::between('An opacity', $opacity, 0, 1);
        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::StampImage;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'image' => $this->image->name,
            'pages' => $this->pages?->value,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'opacity' => $this->opacity,
            'rotation' => $this->rotation,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [$this->image];
    }
}
