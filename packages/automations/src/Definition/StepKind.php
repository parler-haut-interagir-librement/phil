<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

/**
 * What a step does: run an action, wait, stop the run unless a condition holds, or follow one of two paths.
 */
enum StepKind: string
{
    case Action = 'action';
    case Delay = 'delay';
    case Filter = 'filter';
    case Branch = 'branch';
}
