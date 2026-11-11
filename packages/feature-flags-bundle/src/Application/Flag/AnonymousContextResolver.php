<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Override;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;

/**
 * A context with no tenant, no project and no subject: for an application whose flags are on or off for
 * everyone. A targeting rule never matches it, so a flag with rules stays off rather than reaching a context
 * it cannot place.
 */
final readonly class AnonymousContextResolver implements EvaluationContextResolver
{
    #[Override]
    public function current(): EvaluationContext
    {
        return EvaluationContext::anonymous();
    }
}
