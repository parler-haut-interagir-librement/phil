<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Twig;

use Phil\OnboardingBundle\Application\Contract\CurrentUser;
use Phil\OnboardingBundle\Application\Contract\TourPreference;
use Phil\OnboardingBundle\Application\FlowProgress;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Application\StepProgress;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What the checklist and the tour of the current user show: their progress in a flow, the CSRF token and the URLs of
 * the endpoints, and the link of each step. Nothing for a visitor; no tour for a flow finished or dismissed, nor for a
 * user who turned the tours off.
 *
 * @phpstan-type ViewData array{id: string, progress: FlowProgress, token: string, header: string, urls: array{dismiss: string, reset: string, done: array<string, string>, answer: array<string, string>, skip: array<string, string>}, links: array<string, string>}
 */
final readonly class OnboardingView
{
    public function __construct(
        private Onboarding $onboarding,
        private CurrentUser $currentUser,
        private OnboardingRequest $requests,
        private UrlGeneratorInterface $urls,
        private TourPreference $tours,
    ) {
    }

    public function progress(string $flow): ?FlowProgress
    {
        $user = $this->currentUser->id();

        return null === $user ? null : $this->onboarding->progress($user, $flow);
    }

    /**
     * @return ViewData|null
     */
    public function checklist(string $flow): ?array
    {
        $progress = $this->progress($flow);

        return !$progress instanceof FlowProgress || $progress->isDismissed() ? null : $this->data($progress);
    }

    /**
     * @return ViewData|null
     */
    public function tour(string $flow): ?array
    {
        $user = $this->currentUser->id();

        if (null === $user || !$this->tours->wantsTours($user)) {
            return null;
        }

        $progress = $this->onboarding->progress($user, $flow);

        return $progress->isActive() ? $this->data($progress) : null;
    }

    /**
     * @return ViewData
     */
    private function data(FlowProgress $progress): array
    {
        $flow = $progress->flow->id;
        $done = [];
        $answer = [];
        $skip = [];
        $links = [];

        foreach ($progress->steps as $item) {
            $step = $item->step->id;

            if ($item->isTickable()) {
                $done[$step] = $this->urls->generate(Routes::DONE, ['flow' => $flow, 'step' => $step]);
            }

            if ($item->isAnswerable()) {
                $answer[$step] = $this->urls->generate(Routes::ANSWER, ['flow' => $flow, 'step' => $step]);
            }

            if ($item->isSkippable()) {
                $skip[$step] = $this->urls->generate(Routes::SKIP, ['flow' => $flow, 'step' => $step]);
            }

            if (null !== $item->step->route) {
                $links[$step] = $this->link($item);
            }
        }

        return [
            'id' => 'phil-onboarding-'.$flow,
            'progress' => $progress,
            'token' => $this->requests->token(),
            'header' => OnboardingRequest::HEADER,
            'urls' => [
                'dismiss' => $this->urls->generate(Routes::DISMISS, ['flow' => $flow]),
                'reset' => $this->urls->generate(Routes::RESET, ['flow' => $flow]),
                'done' => $done,
                'answer' => $answer,
                'skip' => $skip,
            ],
            'links' => $links,
        ];
    }

    private function link(StepProgress $item): string
    {
        return $this->urls->generate((string) $item->step->route, $item->step->routeParameters);
    }
}
