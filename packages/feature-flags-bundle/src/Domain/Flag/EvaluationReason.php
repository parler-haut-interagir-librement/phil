<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * Why an evaluation came out the way it did, on the reason vocabulary of the OpenFeature specification.
 *
 * An evaluation that could not be made carries the reason `ERROR` and says why in its error code
 * ({@see EvaluationErrorCode}), as OpenFeature separates the two.
 */
enum EvaluationReason: string
{
    /** The flag is active and has no rule: on for every context. */
    case Static = 'STATIC';

    /** The flag is active and a membership rule matched the context. */
    case TargetingMatch = 'TARGETING_MATCH';

    /** The flag is active and a percentage rollout admitted the context. */
    case Split = 'SPLIT';

    /** The flag is active, has rules, and none matched: off. */
    case Default = 'DEFAULT';

    /** The flag is inactive: off for every context, whatever its rules say. */
    case Disabled = 'DISABLED';

    /** The flag could not be evaluated, for the reason its error code gives: off. */
    case Error = 'ERROR';
}
