<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * When the children of an activity are selected or reordered: never, once for the whole session, or for each new
 * attempt on the activity.
 */
enum RandomizationTiming: string
{
    case Never = 'never';
    case Once = 'once';
    case OnEachNewAttempt = 'onEachNewAttempt';
}
