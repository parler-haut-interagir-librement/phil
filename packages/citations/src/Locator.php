<?php

declare(strict_types=1);

namespace Phil\Citations;

/**
 * What the locator of a citation points to in the cited document.
 */
enum Locator: string
{
    case Page = 'page';
    case Chapter = 'chapter';
    case Section = 'section';
    case Paragraph = 'paragraph';
    case Volume = 'volume';
    case Figure = 'figure';
    case Line = 'line';
    case Note = 'note';
}
