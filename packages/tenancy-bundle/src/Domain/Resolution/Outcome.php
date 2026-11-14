<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Resolution;

/**
 * What resolving the active tenant can end in.
 */
enum Outcome: string
{
    /** A tenant is active. */
    case Active = 'active';

    /** Several tenants are reachable and none is chosen: the application shows its selector. */
    case SelectionRequired = 'selection_required';

    /** No tenant is reachable: the account stays authenticated and acts in none. */
    case NoneReachable = 'none_reachable';
}
