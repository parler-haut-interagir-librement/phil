<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

/**
 * Where the grading of a score is: only a `FullyGraded` score is final for the platform's gradebook.
 */
enum GradingProgress: string
{
    case FullyGraded = 'FullyGraded';
    case Pending = 'Pending';
    case PendingManual = 'PendingManual';
    case Failed = 'Failed';
    case NotReady = 'NotReady';
}
