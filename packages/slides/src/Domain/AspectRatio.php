<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

use Phil\PagedMedia\Pdf\PageSize;

/**
 * The shape of a slide: wide, `16:9`, or standard, `4:3`. A slide printed is a page of that shape, 540 points high.
 */
enum AspectRatio: string
{
    case Wide = '16:9';
    case Standard = '4:3';

    /**
     * The page one slide is printed on: 960 × 540 points wide, 720 × 540 points standard.
     */
    public function pageSize(): PageSize
    {
        return match ($this) {
            self::Wide => PageSize::of(960, 540),
            self::Standard => PageSize::of(720, 540),
        };
    }

    /**
     * The ratio as the CSS property `aspect-ratio` writes it.
     */
    public function css(): string
    {
        return str_replace(':', ' / ', $this->value);
    }
}
