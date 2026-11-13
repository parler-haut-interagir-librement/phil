<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\LibreOffice;

/**
 * How a viewer lays the pages out; `twoColumnRight` puts the first page on the right.
 */
enum PageLayout: string
{
    case Default = 'default';
    case SinglePage = 'singlePage';
    case OneColumn = 'oneColumn';
    case TwoColumnRight = 'twoColumnRight';
}
