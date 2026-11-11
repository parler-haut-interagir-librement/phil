<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;

final class CountingResolver implements EvaluationContextResolver
{
    public int $calls = 0;

    public function __construct(
        private readonly EvaluationContext $context,
    ) {
    }

    #[Override]
    public function current(): EvaluationContext
    {
        ++$this->calls;

        return $this->context;
    }
}
