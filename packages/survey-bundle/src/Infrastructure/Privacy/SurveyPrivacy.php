<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\SurveyBundle\Application\Participations;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Response;

/**
 * A person's survey data in the export and the erasure of phil/privacy-bundle: the responses that name them — of the
 * questionnaires that are not anonymous —, with their addenda, and the marks of their participations. An anonymous
 * response names nobody: nothing finds it, so nothing exports or erases it, and that is its promise.
 */
final readonly class SurveyPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'survey';

    public function __construct(
        private ResponseRepository $responses,
        private Participations $participations,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $responses = $this->responses->ofRespondent($subject->value);
        $participations = $this->responses->participationsOf($this->participations->digest($subject->value));
        $export = [];

        if ([] !== $responses) {
            $export['responses'] = array_map($this->exported(...), $responses);
        }

        if ([] !== $participations) {
            $export['participations'] = array_map(static fn (array $participation): array => ['questionnaire' => $participation['questionnaire'], 'subject' => '' === $participation['subject'] ? null : $participation['subject']], $participations);
        }

        return $export;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->responses->eraseRespondent($subject->value, $this->participations->digest($subject->value));

        return ErasureReport::of(self::MODULE, [
            'phil_survey_responses' => ErasureEffect::Deleted,
            'phil_survey_participations' => ErasureEffect::Deleted,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(Response $response): array
    {
        return [
            'id' => $response->id,
            'questionnaire' => $response->questionnaire,
            'subject' => $response->subject,
            'submitted_at' => $response->submittedAt->format(DateTimeInterface::ATOM),
            'ending' => $response->ending,
            'answers' => $response->answers,
            'computed' => $response->computed,
            'addenda' => array_map(static fn (Addendum $addendum): array => ['text' => $addendum->text, 'added_at' => $addendum->addedAt->format(DateTimeInterface::ATOM)], $this->responses->addendaOf($response->id)),
        ];
    }
}
