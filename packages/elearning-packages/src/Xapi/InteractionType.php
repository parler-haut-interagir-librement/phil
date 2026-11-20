<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * The interaction types xAPI defines for an activity of type `cmi.interaction`, as `tincan.xml` names them.
 */
enum InteractionType: string
{
    case TrueFalse = 'true-false';
    case Choice = 'choice';
    case FillIn = 'fill-in';
    case LongFillIn = 'long-fill-in';
    case Matching = 'matching';
    case Performance = 'performance';
    case Sequencing = 'sequencing';
    case Likert = 'likert';
    case Numeric = 'numeric';
    case Other = 'other';
}
