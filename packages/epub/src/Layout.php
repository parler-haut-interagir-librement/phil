<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * How the content of a document is laid out: reflowed to the screen by the reading system, or pre-paginated — a fixed
 * page whose size its viewport gives, as for a comic, a picture book or a game sheet.
 */
enum Layout: string
{
    case Reflowable = 'reflowable';
    case PrePaginated = 'pre-paginated';
}
