<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * The cover of the book: an image, marked as the cover image in the package document, shown alone in a cover document
 * at the head of the reading order, with its text alternative.
 */
final readonly class Cover
{
    /**
     * @throws InvalidBook
     */
    public function __construct(public Asset $image, public string $alt)
    {
        if (AssetKind::Image !== $image->kind) {
            throw InvalidBook::because(\sprintf('A cover is an image; "%s" is a %s.', $image->path, $image->kind->value));
        }

        if ('' === trim($alt)) {
            throw InvalidBook::because('A cover needs a text alternative.');
        }
    }
}
