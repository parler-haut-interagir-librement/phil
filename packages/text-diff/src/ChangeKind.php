<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * What a change does: puts text where there was none, takes text out, or puts other text in its place.
 */
enum ChangeKind: string
{
    case Insertion = 'insertion';
    case Deletion = 'deletion';
    case Replacement = 'replacement';
}
