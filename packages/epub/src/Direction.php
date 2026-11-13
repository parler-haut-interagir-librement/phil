<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The direction of the text and of the page progression: left to right, right to left, or left to the reading system.
 */
enum Direction: string
{
    case Default = 'default';
    case LeftToRight = 'ltr';
    case RightToLeft = 'rtl';
}
