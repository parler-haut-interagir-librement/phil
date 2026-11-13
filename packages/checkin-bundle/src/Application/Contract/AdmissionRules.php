<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Application\Contract;

use Phil\Checkin\CheckpointId;

/**
 * The admission rule of each checkpoint: an ExpressionLanguage expression a credential meets to enter there, on top of
 * the admission policy. The bundle reads them from its configuration; an application that keeps them elsewhere — in
 * its database, edited by its staff — names its own service.
 */
interface AdmissionRules
{
    /**
     * The expression of a checkpoint, or null when the checkpoint has none and admits on the policy alone.
     */
    public function ruleFor(CheckpointId $checkpoint): ?string;
}
