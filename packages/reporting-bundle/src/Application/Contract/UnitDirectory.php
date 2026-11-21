<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Domain\Training\UnitNode;

/**
 * The tree of the organisational units, as the training reports read it: the path of a unit, from its root. Without
 * one, each unit is reported alone, named by its identifier.
 */
interface UnitDirectory
{
    /**
     * The units from the root to the unit itself, the unit last; an empty list for a unit the directory does not know.
     *
     * @return list<UnitNode>
     */
    public function path(string $unit): array;
}
