<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * How an image of another shape than its box is scaled into it; it keeps its proportions either way.
 */
enum Fit: string
{
    /** The whole image, as large as the box allows: the box may keep empty bands. */
    case Contain = 'contain';

    /** The whole box, covered: the image may be cropped, along the alignment given. */
    case Cover = 'cover';
}
