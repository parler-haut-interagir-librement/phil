<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\RecordOutcome;
use Phil\SurveyBundle\Domain\ValueObject\Response;

/**
 * Where the responses, their addenda, the counts of the quotas and the marks of participation are kept.
 *
 * A mark of participation says that a respondent answered a questionnaire, for a subject, and nothing else: a keyed
 * digest of the respondent, the questionnaire and the subject, with no time and no reference to the response.
 */
interface ResponseRepository
{
    /**
     * Records a response at once with its mark of participation and its counts: the mark taken if free, each quota's
     * counter raised if under its limit, then the response kept — or nothing at all.
     *
     * @param string|null $participation the digest of the respondent's participation, when known
     * @param list<Quota> $quotas        the quotas the response counts in
     */
    public function record(Response $response, ?string $participation, array $quotas): RecordOutcome;

    public function hasParticipated(string $questionnaire, string $subject, string $participation): bool;

    public function find(string $id): ?Response;

    /**
     * Adds to the response of the questionnaire whose addendum token has this digest, up to the number of addenda a
     * response takes.
     *
     * @return bool whether a response took it
     */
    public function addAddendum(string $questionnaire, string $digest, string $text, DateTimeImmutable $at, int $max): bool;

    /**
     * @return list<Addendum> the addenda of the response, in order
     */
    public function addendaOf(string $response): array;

    /**
     * How many responses the questionnaire has — about the subject, when given.
     */
    public function count(string $questionnaire, ?string $subject = null): int;

    /**
     * The stored answers of one question, read one response at a time: `null` when not answered.
     *
     * @return iterable<mixed>
     */
    public function answers(string $questionnaire, string $question, ?string $subject = null): iterable;

    /**
     * @return array<string, int> how many responses each quota took, by name
     */
    public function quotaCounts(string $questionnaire): array;

    /**
     * @return list<Response> the responses that name the respondent, oldest first
     */
    public function ofRespondent(string $respondent): array;

    /**
     * Deletes the responses that name the respondent, with their addenda, and the marks of the participations of this
     * digest.
     *
     * @return int how many responses were deleted
     */
    public function eraseRespondent(string $respondent, string $participations): int;

    /**
     * @return list<array{questionnaire: string, subject: string}> the participations of the digest
     */
    public function participationsOf(string $participations): array;

    /**
     * Deletes, up to the limit, the responses of the questionnaire sent before the instant, with their addenda.
     *
     * @return int how many responses were deleted
     */
    public function purgeBefore(string $questionnaire, DateTimeImmutable $before, int $limit): int;
}
