<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

/**
 * The kind of a condition in a trace.
 */
enum NodeKind: string
{
    case All = 'all';

    case Any = 'any';

    case Not = 'not';

    case Leaf = 'leaf';
}
