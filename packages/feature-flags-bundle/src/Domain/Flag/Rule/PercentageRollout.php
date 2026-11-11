<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag\Rule;

use Override;
use Phil\FeatureFlagsBundle\Domain\Flag\Bucket;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

/**
 * Matches a stable share of the values of one attribute: the same tenant stays in or out of a 20 % rollout
 * on every request, and stays in when the rollout grows to 50 %.
 */
final readonly class PercentageRollout implements Rule
{
    public function __construct(
        public TargetingAttribute $attribute,
        public Percentage $percentage,
    ) {
    }

    #[Override]
    public function matches(FlagName $flag, EvaluationContext $context): bool
    {
        $value = $context->get($this->attribute);

        return null !== $value && $this->percentage->admits(Bucket::of($flag, $value));
    }

    #[Override]
    public function matchReason(): EvaluationReason
    {
        return EvaluationReason::Split;
    }

    #[Override]
    public function reads(): TargetingAttribute
    {
        return $this->attribute;
    }
}
