<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

/**
 * What happened to a block between two documents. The value is also the end of the class the HTML gives the block,
 * `text-diff-<value>`.
 */
enum BlockChangeKind: string
{
    case Unchanged = 'unchanged';
    case Changed = 'changed';
    case Added = 'added';
    case Removed = 'removed';
    case Moved = 'moved';
}
