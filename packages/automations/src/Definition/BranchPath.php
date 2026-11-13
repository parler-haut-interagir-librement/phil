<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

/**
 * One of the two paths of a branch.
 */
enum BranchPath: string
{
    case Then = 'then';
    case Else = 'else';
}
