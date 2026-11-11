<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Toggle;

use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;

/**
 * Writes the audit line of a change to a flag, before the change is written. An auditor that cannot write the
 * line throws, and the change is not applied. The adapter belongs to the application, and so does the question
 * of whether the write and the audit line share one transaction.
 */
interface ToggleAuditor
{
    public function record(ToggleRecord $record): void;

    public function recordEdit(EditRecord $record): void;
}
