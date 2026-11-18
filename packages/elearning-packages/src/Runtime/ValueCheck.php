<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * How the value of an element is checked: against its own pattern, or against the pattern of the type of its
 * interaction — a correct response or a learner's response of SCORM 2004, whose format depends on whether the
 * interaction is a choice, a true-false, a numeric….
 */
enum ValueCheck: string
{
    case Pattern = 'pattern';
    case InteractionPattern = 'interaction-pattern';
    case InteractionResponse = 'interaction-response';
}
