<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag\Rule;

use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

/**
 * A targeting rule of an active flag. A rule that cannot read the attribute it needs does not match: a
 * flag fails closed for a context it cannot place.
 */
interface Rule
{
    public function matches(FlagName $flag, EvaluationContext $context): bool;

    /**
     * The reason an evaluation gives when this rule is the one that matched: `TARGETING_MATCH` for a rule
     * that picks contexts out, `SPLIT` for one that admits a share of them.
     */
    public function matchReason(): EvaluationReason;

    /**
     * The attribute of the context the rule reads: what tells whether the flag can be evaluated before that
     * attribute is known, as a route condition is.
     */
    public function reads(): TargetingAttribute;
}
