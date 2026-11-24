<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Override;
use Phil\FormsBundle\Application\CurrentSubject;

/**
 * The respondent of an application without Symfony Security: nobody is signed in, so only the questionnaires that
 * need no member are answered.
 */
final readonly class NoRespondent implements CurrentSubject
{
    #[Override]
    public function current(): ?string
    {
        return null;
    }
}
