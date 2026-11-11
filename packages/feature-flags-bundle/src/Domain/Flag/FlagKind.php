<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\Enums\Traits\AsValuableEnumTrait;

/**
 * What a flag is for, on the four categories of Pete Hodgson's *Feature Toggles*. The kind says how long a flag
 * should live and who owns it; it changes nothing in its evaluation.
 */
enum FlagKind: string
{
    use AsValuableEnumTrait;

    /** Ships a feature before it is released; removed once the feature is on for everyone. */
    case Release = 'release';

    /** Splits contexts between variants to compare them; removed when the experiment concludes. */
    case Experiment = 'experiment';

    /** Lets an operator turn a feature off in production; most are retired once the feature is trusted. */
    case Ops = 'ops';

    /** Opens a feature to a set of tenants, projects or subjects, such as beta testers; may live long. */
    case Permission = 'permission';
}
