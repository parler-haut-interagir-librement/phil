<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

/**
 * What deleting a medium does to a record that shows it, column by column: `restrict` refuses the deletion, `set_null`
 * empties the column, `keep` leaves the identifier of a medium that no longer exists, which pages then skip.
 */
enum DeleteBehaviour: string
{
    case Restrict = 'restrict';
    case SetNull = 'set_null';
    case Keep = 'keep';
}
