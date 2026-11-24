<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use SensitiveParameter;

/**
 * The digest that marks a respondent's participations: an HMAC-SHA-256 of their identifier under the application's
 * secret, so that the marks name nobody to whoever reads the table, and a request about a person finds and erases
 * them. A mark has no time and no reference to a response: it says that someone answered, never what.
 */
final readonly class Participations
{
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw InvalidQuestionnaire::because('The secret of the participations is at least 16 bytes.');
        }
    }

    public function digest(string $respondent): string
    {
        return hash_hmac('sha256', 'phil_survey.participation|'.$respondent, $this->secret);
    }
}
