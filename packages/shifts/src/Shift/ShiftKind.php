<?php

declare(strict_types=1);

namespace Phil\Shifts\Shift;

/**
 * What a shift asks of a volunteer:
 *
 * - `Fixed`: to be there from its start to its end — the info desk from 9:00 to 12:00;
 * - `Task`: to do a task some time within its window, counted for the minutes it is estimated at — set up the tables
 *   before Saturday noon;
 * - `Duration`: to serve a number of minutes, when the volunteer chooses, within its window — two hours at the
 *   cloakroom any time on Saturday.
 */
enum ShiftKind: string
{
    case Fixed = 'fixed';
    case Task = 'task';
    case Duration = 'duration';
}
