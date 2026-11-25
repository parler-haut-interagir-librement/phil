<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use SensitiveParameter;

/**
 * The digest that marks a respondent's participations: an HMAC-SHA-256 of their identifier under a key derived from the
 * application's secret, so that the marks name nobody to whoever reads the table, and a request about a person finds
 * and erases them. A mark has no time and no reference to a response: it says that someone answered, never what.
 */
final readonly class Participations
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_survey.participation';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw InvalidQuestionnaire::because('The secret of the participations is at least 16 bytes.');
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    public function digest(string $respondent): string
    {
        return hash_hmac('sha256', 'phil_survey.participation|'.$respondent, $this->key);
    }
}
