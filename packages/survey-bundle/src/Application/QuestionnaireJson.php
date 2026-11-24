<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use JsonException;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * The exchange format of a questionnaire: `{"format": "phil-survey/1", "questionnaire": {…}}`, the questionnaire as
 * `schema/questionnaire.schema.json` describes it. Reading is bounded — 1 MiB of JSON, 64 levels deep — and strict:
 * an unknown member, a value of the wrong type or a condition outside the JsonLogic subset is refused with
 * InvalidQuestionnaire, never another exception.
 */
final class QuestionnaireJson
{
    public const string FORMAT = 'phil-survey/1';

    public const int MAX_BYTES = 1_048_576;

    public const int MAX_DEPTH = 64;

    private function __construct()
    {
    }

    /**
     * @throws JsonException never for a questionnaire built
     */
    public static function encode(Questionnaire $questionnaire): string
    {
        return json_encode(['format' => self::FORMAT, 'questionnaire' => $questionnaire->toArray()], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @throws InvalidQuestionnaire when the JSON is not a questionnaire of this format within the bounds
     */
    public static function decode(string $json): Questionnaire
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire is at most %d bytes of JSON.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire is valid JSON, at most %d levels deep: %s', self::MAX_DEPTH, $jsonException->getMessage()));
        }

        $document = Read::map($data, 'the document', ['format', 'questionnaire']);

        if (self::FORMAT !== ($document['format'] ?? null)) {
            throw InvalidQuestionnaire::because(\sprintf('The document is of the format "%s".', self::FORMAT));
        }

        return Questionnaire::fromArray($document['questionnaire'] ?? null);
    }
}
