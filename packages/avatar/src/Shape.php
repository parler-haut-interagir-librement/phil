<?php

declare(strict_types=1);

namespace Phil\Avatar;

/**
 * The outline of an avatar.
 */
enum Shape: string
{
    case Square = 'square';

    /** A square with rounded corners, a sixth of its side. */
    case Rounded = 'rounded';

    case Circle = 'circle';
}
