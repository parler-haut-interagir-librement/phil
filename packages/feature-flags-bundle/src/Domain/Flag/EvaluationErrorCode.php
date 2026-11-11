<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * Why an evaluation whose reason is `ERROR` could not be made, on the error code vocabulary of the
 * OpenFeature specification.
 */
enum EvaluationErrorCode: string
{
    /** No provider knows the flag: off, so that an unknown feature stays hidden. */
    case FlagNotFound = 'FLAG_NOT_FOUND';

    /** A provider holds the flag but its definition no longer reads as one (a stored row edited by hand): off. */
    case ParseError = 'PARSE_ERROR';

    /** A provider or the state store failed while answering (a database that cannot be reached): off. */
    case General = 'GENERAL';
}
