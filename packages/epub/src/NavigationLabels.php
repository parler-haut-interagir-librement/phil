<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The words the navigation document shows, in the language of the book: the heading of the table of contents, of the
 * landmarks and of the page list, and the labels of the cover, table of contents and start landmarks.
 */
final readonly class NavigationLabels
{
    public function __construct(
        public string $contents = 'Contents',
        public string $landmarks = 'Landmarks',
        public string $pages = 'Pages',
        public string $cover = 'Cover',
        public string $tableOfContents = 'Table of contents',
        public string $start = 'Start of content',
    ) {
    }
}
