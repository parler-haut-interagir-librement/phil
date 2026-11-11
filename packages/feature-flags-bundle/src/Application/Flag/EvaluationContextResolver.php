<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;

/**
 * Builds the evaluation context of the current request: from the security token, the active tenant, the
 * project the request is for. The adapter belongs to the application.
 */
interface EvaluationContextResolver
{
    public function current(): EvaluationContext;
}
