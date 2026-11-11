<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * What an operation of a difference does with its tokens: keeps them, takes them out of the old text, or puts them in
 * the new one.
 */
enum OperationKind: string
{
    case Equal = 'equal';
    case Delete = 'delete';
    case Insert = 'insert';
}
