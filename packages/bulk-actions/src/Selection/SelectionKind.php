<?php

declare(strict_types=1);

namespace Phil\BulkActions\Selection;

/**
 * How a selection names its items.
 */
enum SelectionKind: string
{
    /** A bounded list of identifiers, each with the version the person saw when the target keeps one. */
    case Identifiers = 'identifiers';

    /** Every item of a filter, less the ones the person unticked, read again when the run executes. */
    case Filter = 'filter';
}
