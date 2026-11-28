<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

/**
 * What became of a condition during an evaluation.
 */
enum Outcome: string
{
    case Passed = 'passed';

    case Failed = 'failed';

    /** Not evaluated: an earlier sibling already decided its `All` or its `Any`. */
    case Skipped = 'skipped';
}
