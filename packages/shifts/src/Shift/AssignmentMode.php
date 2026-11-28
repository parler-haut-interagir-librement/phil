<?php

declare(strict_types=1);

namespace Phil\Shifts\Shift;

/**
 * How the places of a shift are given:
 *
 * - `SelfSignUp`: a volunteer takes a free place, first come, first served, then waits in the reserve;
 * - `Assigned`: a coordinator gives each place;
 * - `Interest`: volunteers say they are interested, and the places are allocated among them — by a coordinator, or by
 *   an allocator that balances the minutes each one already holds.
 *
 * A coordinator may assign a place in every mode.
 */
enum AssignmentMode: string
{
    case SelfSignUp = 'self_sign_up';
    case Assigned = 'assigned';
    case Interest = 'interest';
}
