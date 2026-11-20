<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Xapi;

/**
 * The verbs of ADL an xAPI activity reports its outcome with, by their IRI, and the one this platform refuses: a
 * statement that voids another would take back an outcome already recorded.
 */
enum XapiVerb: string
{
    case Completed = 'http://adlnet.gov/expapi/verbs/completed';
    case Passed = 'http://adlnet.gov/expapi/verbs/passed';
    case Failed = 'http://adlnet.gov/expapi/verbs/failed';
    case Voided = 'http://adlnet.gov/expapi/verbs/voided';
}
