<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\Enums\Traits\AsValuableEnumTrait;

/**
 * The attribute of an `EvaluationContext` that a rule reads.
 *
 * A rollout by tenant keeps every member of a tenant on the same side of the flag; a rollout by subject
 * splits them. The rule, not the context, says which one it means.
 */
enum TargetingAttribute: string
{
    use AsValuableEnumTrait;

    case Tenant = 'tenant';
    case Project = 'project';
    case Subject = 'subject';
}
