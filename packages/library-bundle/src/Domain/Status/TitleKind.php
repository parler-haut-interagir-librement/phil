<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Status;

/**
 * What a title of the catalogue is: a game, a book, or something else lent — a puzzle, a film, a tool.
 */
enum TitleKind: string
{
    case Game = 'game';
    case Book = 'book';
    case Other = 'other';
}
