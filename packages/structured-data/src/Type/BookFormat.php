<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

/**
 * The form a book is published in: the `bookFormat` of a `Book`.
 */
enum BookFormat: string
{
    case Hardcover = 'https://schema.org/Hardcover';
    case Paperback = 'https://schema.org/Paperback';
    case EBook = 'https://schema.org/EBook';
    case Audiobook = 'https://schema.org/AudiobookFormat';
    case GraphicNovel = 'https://schema.org/GraphicNovel';
}
