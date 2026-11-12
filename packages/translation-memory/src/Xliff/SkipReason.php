<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

/**
 * Why a unit of an imported file changed nothing:
 *
 * - `unknown-key`: no segment of the document has its key;
 * - `source-changed`: the source of the segment is no longer the one the file was made from;
 * - `no-target`: the unit has no target;
 * - `unchanged`: the unit brings back the target and state the segment already has, or the same target only proposed.
 */
enum SkipReason: string
{
    case UnknownKey = 'unknown-key';
    case SourceChanged = 'source-changed';
    case NoTarget = 'no-target';
    case Unchanged = 'unchanged';
}
