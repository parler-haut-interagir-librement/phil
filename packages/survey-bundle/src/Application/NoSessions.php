<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Override;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;

/**
 * The sessions of an application that names none: a session's feedback is then never open.
 */
final readonly class NoSessions implements FeedbackSessions
{
    #[Override]
    public function session(string $subject): ?array
    {
        return null;
    }
}
