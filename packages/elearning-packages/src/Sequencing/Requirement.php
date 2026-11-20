<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * When a child counts in a rollup, `<adlseq:rollupConsiderations>`: always, once attempted, unless a skip rule
 * applies to it, or unless it is suspended.
 */
enum Requirement: string
{
    case Always = 'always';
    case IfAttempted = 'ifAttempted';
    case IfNotSkipped = 'ifNotSkipped';
    case IfNotSuspended = 'ifNotSuspended';
}
