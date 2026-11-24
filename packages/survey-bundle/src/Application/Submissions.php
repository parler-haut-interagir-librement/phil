<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Exception\InvalidResponse;
use Phil\SurveyBundle\Domain\ValueObject\Ending;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\Response;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Takes a response, the server judging whatever the browser showed:
 *
 * 1. the questionnaire is open — its window, and for a session's feedback the session's window —, and the respondent
 *    may answer it: a member when it needs one;
 * 2. phil/form-logic judges the answer as the browser collected it: the hidden questions are dropped, unchecked, and
 *    the computed variables computed;
 * 3. each question shown checks its answer, every error at once;
 * 4. the first ending whose condition holds is chosen, and the quotas the answer meets are found;
 * 5. the response is recorded at once with the respondent's mark and the counts of its quotas — or, a quota full, not
 *    kept, the respondent shown that quota's ending.
 *
 * An anonymous response keeps no respondent, a random identifier — a UUID v4, which holds no time — and the day only as
 * its instant; the respondent's mark, kept apart, has no time and no reference to it.
 */
final readonly class Submissions
{
    /** How many addenda a response takes. */
    public const int MAX_ADDENDA = 10;

    public function __construct(
        private ResponseRepository $responses,
        private Participations $participations,
        private RedirectPolicy $redirects,
        private ClockInterface $clock,
        private FeedbackSessions $sessions = new NoSessions(),
    ) {
    }

    /**
     * Whether the questionnaire takes a response now — about the subject, for a session's feedback.
     */
    public function isOpen(Questionnaire $questionnaire, ?string $subject = null): bool
    {
        $now = $this->clock->now();

        if (!$questionnaire->isOpenAt($now)) {
            return false;
        }

        if (!$questionnaire->kind->needsSubject()) {
            return true;
        }

        $session = null === $subject ? null : $this->sessions->session($subject);

        if (null === $session) {
            return false;
        }

        [$start, $end] = $session;

        return $now >= $start->sub(new DateInterval('PT'.$questionnaire->opensBefore.'M'))
            && $now <= $end->add(new DateInterval('PT'.$questionnaire->closesAfter.'M'));
    }

    /**
     * Whether the respondent answered already — about the subject, when given.
     */
    public function hasAnswered(Questionnaire $questionnaire, ?string $respondent, ?string $subject = null): bool
    {
        return null !== $respondent && $this->responses->hasParticipated($questionnaire->key, $subject ?? '', $this->participations->digest($respondent));
    }

    /**
     * @param array<array-key, mixed> $input      the submitted values, by question name
     * @param string|null             $respondent the signed-in member, kept only when the questionnaire is not anonymous
     * @param string|null             $subject    what the response is about, such as a session
     *
     * @throws SurveyRefused   when the questionnaire is closed, needs a member or a subject, or was answered already
     * @throws InvalidResponse with the error of each question refused
     */
    public function submit(Questionnaire $questionnaire, array $input, ?string $respondent = null, ?string $subject = null): Receipt
    {
        if ($questionnaire->kind->needsSubject() && null === $subject) {
            throw SurveyRefused::subjectRequired();
        }

        if (!$this->isOpen($questionnaire, $subject)) {
            throw SurveyRefused::closed();
        }

        if ($questionnaire->needsMember() && null === $respondent) {
            throw SurveyRefused::memberRequired();
        }

        [$answers, $computed] = $this->judge($questionnaire, $input);
        $data = [...$answers, ...$computed];
        $ending = $questionnaire->logic->ending($data);
        $token = $questionnaire->addenda ? sodium_bin2hex(random_bytes(32)) : null;
        $response = new Response(
            Uuid::v4()->toRfc4122(),
            $questionnaire->key,
            $subject,
            $questionnaire->isAnonymous() ? null : $respondent,
            $ending->name,
            $answers,
            $computed,
            null === $token ? null : hash('sha256', $token),
            $this->instant($questionnaire),
        );
        $outcome = $this->responses->record($response, null === $respondent ? null : $this->participations->digest($respondent), $questionnaire->logic->quotasMet($data));

        if ($outcome->alreadyAnswered) {
            throw SurveyRefused::alreadyAnswered();
        }

        if (null !== $outcome->fullQuota) {
            $quota = $questionnaire->quota($outcome->fullQuota);
            $screened = $quota instanceof Quota ? $questionnaire->ending($quota->ending) : null;

            return new Receipt($screened ?? $ending, false, fullQuota: $outcome->fullQuota);
        }

        return new Receipt($ending, true, $response->id, $this->redirect($ending), $token, $computed);
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @return array{array<string, mixed>, array<string, mixed>} the stored answers of the questions shown, and the computed variables
     *
     * @throws InvalidResponse with the error of each question refused
     */
    private function judge(Questionnaire $questionnaire, array $input): array
    {
        $collected = [];

        foreach ($questionnaire->questions() as $question) {
            $collected[$question->name] = $question->collected($input[$question->name] ?? null);
        }

        $verdict = $questionnaire->logic->judge($collected);
        $answers = [];
        $errors = [];

        foreach ($questionnaire->questions() as $question) {
            if (!$verdict->isShown($question->name)) {
                continue;
            }

            $accepted = $question->accept($input[$question->name] ?? null);

            if (null !== $accepted['error']) {
                $errors[$question->name] = $accepted['error'];
            } elseif (null !== $accepted['value']) {
                $answers[$question->name] = $accepted['value'];
            }
        }

        if ([] !== $errors) {
            throw InvalidResponse::withErrors($errors);
        }

        return [$answers, $verdict->computed];
    }

    private function instant(Questionnaire $questionnaire): DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));

        return $questionnaire->isAnonymous() ? $now->setTime(0, 0) : $now;
    }

    private function redirect(Ending $ending): ?string
    {
        if (null === $ending->redirect) {
            return null;
        }

        try {
            return $this->redirects->check($ending->redirect);
        } catch (InvalidQuestionnaire) {
            // The origins allowed changed since the questionnaire was saved: the ending is shown instead.
            return null;
        }
    }
}
