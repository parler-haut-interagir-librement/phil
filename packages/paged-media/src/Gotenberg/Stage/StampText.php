<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Draws a line of text over pages: a watermark, a label, a status. The position counts in points from the bottom left
 * corner of the page, and the rotation in degrees counter-clockwise around it.
 */
final readonly class StampText implements Stage
{
    public ?Pages $pages;

    /**
     * @param string|null $color   `#rrggbb`; black when unset
     * @param float|null  $opacity from 0 to 1; unset or 0 draws the text opaque
     */
    public function __construct(
        public string $text,
        public float $x = 0,
        public float $y = 0,
        public ?float $fontSize = null,
        public ?StampFont $font = null,
        public ?string $color = null,
        public ?float $opacity = null,
        public ?float $rotation = null,
        Pages|string|null $pages = null,
    ) {
        Check::notEmpty('The text of a stamp', $text);
        Check::above('A font size', $fontSize, 0);
        Check::color('The colour of a stamp', $color);
        Check::between('An opacity', $opacity, 0, 1);
        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::StampText;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'text' => $this->text,
            'pages' => $this->pages?->value,
            'x' => $this->x,
            'y' => $this->y,
            'fontSize' => $this->fontSize,
            'font' => $this->font?->value,
            'color' => $this->color,
            'opacity' => $this->opacity,
            'rotation' => $this->rotation,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
