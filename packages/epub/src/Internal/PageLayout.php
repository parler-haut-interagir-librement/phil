<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Layout;
use Phil\Epub\PageSpread;
use Phil\Epub\Viewport;

/**
 * The layout of each chapter of a book: its viewport when pre-paginated, and the properties of its place in the spine.
 *
 * @internal
 */
final class PageLayout
{
    /**
     * The viewport of a chapter and the properties of its place in the spine: its layout when it differs from the
     * book's, its side of the spread. A pre-paginated chapter has a viewport, its own or the book's; a reflowable one
     * has none.
     *
     * @return array{?Viewport, list<string>}
     *
     * @throws InvalidBook
     */
    public static function of(Book $book, Chapter $chapter, string $path): array
    {
        $layout = $chapter->layout ?? $book->rendition->layout;
        $properties = !$chapter->layout instanceof Layout || $chapter->layout === $book->rendition->layout ? [] : ['rendition:layout-'.$layout->value];

        if ($chapter->pageSpread instanceof PageSpread) {
            $properties[] = $chapter->pageSpread->value;
        }

        if (Layout::Reflowable === $layout) {
            if ($chapter->viewport instanceof Viewport) {
                throw InvalidBook::because(\sprintf('The chapter "%s" is reflowable: a viewport is for a pre-paginated chapter.', $path));
            }

            return [null, $properties];
        }

        $viewport = $chapter->viewport ?? $book->rendition->viewport ?? throw InvalidBook::because(\sprintf('The pre-paginated chapter "%s" needs a viewport, its own or the book\'s.', $path));

        return [$viewport, $properties];
    }
}
