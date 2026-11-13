<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * A content document of the book: a fragment of HTML, as found in the body of a page, turned into XHTML when the book
 * is written. Its title is the `<title>` of the document, its first heading's text when not given; its file name is
 * `chapter-N.xhtml` when not given; its landmark marks its role; a chapter left out of the table of contents or of the
 * linear reading order is still in the book.
 *
 * A chapter may carry a media overlay, the narration synchronised with its text. In a fixed-layout book, or when its
 * own layout is pre-paginated, it may give its viewport — the book's otherwise — and the side of the spread it takes.
 */
final readonly class Chapter
{
    /**
     * @throws InvalidBook
     */
    public function __construct(
        public string $html,
        public ?string $title = null,
        public ?string $fileName = null,
        public ?LandmarkType $landmark = null,
        public bool $inToc = true,
        public bool $linear = true,
        public ?string $language = null,
        public ?MediaOverlay $overlay = null,
        public ?Layout $layout = null,
        public ?Viewport $viewport = null,
        public ?PageSpread $pageSpread = null,
    ) {
        if (null !== $fileName && !str_ends_with($fileName, '.xhtml')) {
            throw InvalidBook::because(\sprintf('A chapter file name ends with ".xhtml"; "%s" given.', $fileName));
        }

        if (null !== $language) {
            Metadata::checkLanguage($language);
        }
    }
}
