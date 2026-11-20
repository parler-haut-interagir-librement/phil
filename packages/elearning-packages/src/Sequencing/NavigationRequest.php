<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The navigation requests of SCORM 2004 a platform hands the sequencer: begin the session, resume a suspended one,
 * move by flow, choose an activity, leave the current one, leave the whole tree, or suspend it.
 */
enum NavigationRequest: string
{
    case Start = 'start';
    case ResumeAll = 'resumeAll';
    case Continue = 'continue';
    case Previous = 'previous';
    case Choice = 'choice';
    case Exit = 'exit';
    case ExitAll = 'exitAll';
    case SuspendAll = 'suspendAll';
}
