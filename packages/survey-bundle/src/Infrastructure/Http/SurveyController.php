<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Http;

use Phil\FormsBundle\Application\CurrentSubject;
use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Application\Results;
use Phil\SurveyBundle\Application\Submissions;
use Phil\SurveyBundle\Domain\Exception\InvalidResponse;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The pages of a questionnaire, plain forms that work without JavaScript: the questionnaire, its anonymity said before
 * the first question; the ending reached, or the redirect it leads to, with the results when the questionnaire shows
 * them; and the addendum. The respondent is the member phil/forms-bundle's `CurrentSubject` gives.
 */
final readonly class SurveyController
{
    public const string SHOW = 'phil_survey_show';

    public const string SUBMIT = 'phil_survey_submit';

    public const string ADDENDUM = 'phil_survey_addendum';

    private const string SUBJECT = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,190}$/D';

    public function __construct(
        private Questionnaires $questionnaires,
        private Submissions $submissions,
        private Results $results,
        private Addenda $addenda,
        private CurrentSubject $respondent,
        private SurveyGuard $guard,
        private Environment $twig,
    ) {
    }

    public function show(Request $request, string $key): Response
    {
        $questionnaire = $this->questionnaire($key);
        $subject = $this->subject($request->query->get('subject'));

        return $this->form($questionnaire, $subject, [], [], $this->refusal($questionnaire, $subject), Response::HTTP_OK);
    }

    public function submit(Request $request, string $key): Response
    {
        $questionnaire = $this->questionnaire($key);
        $subject = $this->subject($request->request->get('subject'));
        $input = $request->request->all('answers');

        try {
            $this->guard->assertTrusted($request->request->getString('_token'));
            $receipt = $this->submissions->submit($questionnaire, $input, $this->respondent->current(), $subject);
        } catch (InvalidResponse $invalidResponse) {
            return $this->form($questionnaire, $subject, $input, $invalidResponse->errors, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SurveyRefused $surveyRefused) {
            return $this->form($questionnaire, $subject, [], [], $surveyRefused->reason, SurveyRefused::UNTRUSTED_REQUEST === $surveyRefused->reason ? Response::HTTP_FORBIDDEN : Response::HTTP_CONFLICT);
        }

        if (null !== $receipt->redirect) {
            return new RedirectResponse($receipt->redirect, Response::HTTP_SEE_OTHER);
        }

        return new Response($this->twig->render('@PhilSurvey/ending.html.twig', [
            'questionnaire' => $questionnaire,
            'receipt' => $receipt,
            'results' => $receipt->kept && $questionnaire->publicResults ? $this->results->public($questionnaire, $subject) : null,
            'token' => $this->guard->token(),
        ]));
    }

    public function addendum(Request $request, string $key): Response
    {
        $questionnaire = $this->questionnaire($key);

        try {
            $this->guard->assertTrusted($request->request->getString('_token'));
            $this->addenda->add($questionnaire, $request->request->getString('receipt'), $request->request->getString('text'));
            $refused = null;
        } catch (SurveyRefused $surveyRefused) {
            $refused = $surveyRefused->reason;
        }

        return new Response(
            $this->twig->render('@PhilSurvey/addendum.html.twig', ['questionnaire' => $questionnaire, 'refused' => $refused]),
            null === $refused ? Response::HTTP_OK : Response::HTTP_CONFLICT,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<string, string>   $errors
     */
    private function form(Questionnaire $questionnaire, ?string $subject, array $values, array $errors, ?string $refused, int $status): Response
    {
        return new Response($this->twig->render('@PhilSurvey/questionnaire.html.twig', [
            'questionnaire' => $questionnaire,
            'subject' => $subject,
            'values' => $values,
            'errors' => $errors,
            'refused' => $refused,
            'token' => $this->guard->token(),
        ]), $status);
    }

    private function refusal(Questionnaire $questionnaire, ?string $subject): ?string
    {
        $respondent = $this->respondent->current();

        return match (true) {
            $questionnaire->kind->needsSubject() && null === $subject => SurveyRefused::SUBJECT_REQUIRED,
            !$this->submissions->isOpen($questionnaire, $subject) => SurveyRefused::CLOSED,
            $questionnaire->needsMember() && null === $respondent => SurveyRefused::MEMBER_REQUIRED,
            $this->submissions->hasAnswered($questionnaire, $respondent, $subject) => SurveyRefused::ALREADY_ANSWERED,
            default => null,
        };
    }

    private function questionnaire(string $key): Questionnaire
    {
        return $this->questionnaires->find($key) ?? throw new NotFoundHttpException('No questionnaire has this key.');
    }

    private function subject(mixed $subject): ?string
    {
        return \is_string($subject) && 1 === preg_match(self::SUBJECT, $subject) ? $subject : null;
    }
}
