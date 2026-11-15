<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

/**
 * Where the learner is in the activity a score is about.
 */
enum ActivityProgress: string
{
    case Initialized = 'Initialized';
    case Started = 'Started';
    case InProgress = 'InProgress';
    case Submitted = 'Submitted';
    case Completed = 'Completed';
}
