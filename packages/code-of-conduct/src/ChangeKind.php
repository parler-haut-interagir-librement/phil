<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * How much a new version of a code changes it.
 */
enum ChangeKind: string
{
    /** A change of substance: every member accepts the code again before they take part. */
    case Major = 'major';

    /** A correction or a clarification: an earlier acceptance still holds. */
    case Minor = 'minor';
}
