<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Cmi5;

/**
 * The IRIs cmi5 defines for its context: the categories of its statements and the extensions of their context.
 */
final class Cmi5Iri
{
    public const string CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/cmi5';

    public const string MOVE_ON_CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/moveon';

    public const string SESSION = 'https://w3id.org/xapi/cmi5/context/extensions/sessionid';

    public const string LAUNCH_MODE = 'https://w3id.org/xapi/cmi5/context/extensions/launchmode';

    public const string LAUNCH_URL = 'https://w3id.org/xapi/cmi5/context/extensions/launchurl';

    public const string MOVE_ON = 'https://w3id.org/xapi/cmi5/context/extensions/moveon';

    public const string LAUNCH_PARAMETERS = 'https://w3id.org/xapi/cmi5/context/extensions/launchparameters';

    public const string MASTERY_SCORE = 'https://w3id.org/xapi/cmi5/context/extensions/masteryscore';

    public const string REASON = 'https://w3id.org/xapi/cmi5/context/extensions/reason';

    public const string LAUNCH_DATA = 'LMS.LaunchData';

    public const string LEARNER_PREFERENCES = 'cmi5LearnerPreferences';

    private function __construct()
    {
    }
}
