<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Contract;

use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;

/**
 * Tells someone that a group needs a look: called for the first event of a group and for a regression.
 */
interface GroupAlerter
{
    public function alert(ErrorGroup $group, Occurrence $occurrence): void;
}
