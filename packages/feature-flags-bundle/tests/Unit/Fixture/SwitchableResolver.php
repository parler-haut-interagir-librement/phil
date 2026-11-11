<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;

/**
 * A resolver whose context a test changes in the middle of a request, as a firewall does when it loads the user.
 */
final class SwitchableResolver implements EvaluationContextResolver
{
    public function __construct(
        public EvaluationContext $context,
    ) {
    }

    #[Override]
    public function current(): EvaluationContext
    {
        return $this->context;
    }
}
