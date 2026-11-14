<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Http;

use Closure;
use Phil\OnboardingBundle\Application\Contract\CurrentUser;
use Phil\OnboardingBundle\Application\Exception\StepRefused;
use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Application\FlowProgress;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Exception\InvalidAnswer;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of the checklist and the tour, for the user of the current request only: a step ticked, answered or
 * skipped, the flow dismissed or started again. A change answers the tour, which asks for JSON, with where the user stands, and a
 * plain form with a redirection to the page it came from on this site, or to `/`.
 *
 * A visitor or a request from another site gets a `403`, an unknown flow or step a `404`, a step that cannot be ticked,
 * answered or skipped, or a progress saved at the same instant by another request, a `409`, an answer the question
 * cannot take a `422`.
 */
final readonly class OnboardingController
{
    public function __construct(
        private Onboarding $onboarding,
        private CurrentUser $currentUser,
        private OnboardingRequest $requests,
    ) {
    }

    public function done(Request $request, string $flow, string $step): Response
    {
        return $this->change($request, fn (string $user): FlowProgress => $this->onboarding->markDone($user, $flow, $step));
    }

    /**
     * The choices come in the form field `choices`, a list of identifiers: `choices[]=news&choices[]=events`.
     */
    public function answer(Request $request, string $flow, string $step): Response
    {
        $choices = $request->request->all()['choices'] ?? [];
        $picked = \is_array($choices) ? array_values(array_filter($choices, \is_string(...))) : [];

        return $this->change($request, fn (string $user): FlowProgress => $this->onboarding->answer($user, $flow, $step, $picked));
    }

    public function skip(Request $request, string $flow, string $step): Response
    {
        return $this->change($request, fn (string $user): FlowProgress => $this->onboarding->skip($user, $flow, $step));
    }

    public function dismiss(Request $request, string $flow): Response
    {
        return $this->change($request, fn (string $user): FlowProgress => $this->onboarding->dismiss($user, $flow));
    }

    public function reset(Request $request, string $flow): Response
    {
        return $this->change($request, fn (string $user): FlowProgress => $this->onboarding->reset($user, $flow));
    }

    /**
     * @param Closure(string): FlowProgress $change
     */
    private function change(Request $request, Closure $change): Response
    {
        $user = $this->currentUser->id();

        if (null === $user || !$this->requests->isTrusted($request)) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $progress = $change($user);
        } catch (UnknownFlow) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        } catch (StepRefused|InvalidProgress|ProgressNotSaved) {
            return new JsonResponse(['error' => 'conflict'], Response::HTTP_CONFLICT);
        } catch (InvalidAnswer) {
            return new JsonResponse(['error' => 'invalid_answer'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (\in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            return new JsonResponse([
                'flow' => $progress->flow->id,
                'done' => $progress->done(),
                'total' => $progress->total(),
                'current' => $progress->current()?->step->id,
                'finished' => $progress->isFinished(),
                'dismissed' => $progress->isDismissed(),
            ], headers: ['Cache-Control' => 'no-store']);
        }

        return new RedirectResponse($this->back($request), Response::HTTP_SEE_OTHER);
    }

    /**
     * The page the form was on, when it is on this site; the home page otherwise.
     */
    private function back(Request $request): string
    {
        $referer = $request->headers->get('Referer') ?? '';

        return str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : '/';
    }
}
