<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * How a session begins, as `cmi.entry` says: the first session of an attempt, a session resuming a suspended one, or
 * another session of an attempt that was neither ended nor suspended.
 */
enum Entry: string
{
    case AbInitio = 'ab-initio';
    case Resume = 'resume';
    case Other = '';
}
