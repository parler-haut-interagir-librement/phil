<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;

/**
 * Told that a variant of an experiment was shown: once per flag and context per request, when the flag
 * is first evaluated for that context.
 *
 * **The flag and the variant, and nothing else.** No context, no tenant, no subject: an exposure is a count to
 * add to an aggregate (variant `treatment` of `new-checkout` seen once more today), never a record of who saw
 * what. The adapter belongs to the application, which compares the arms by its own aggregate measures.
 */
interface ExposureRecorder
{
    public function record(FlagName $flag, VariantName $variant): void;
}
