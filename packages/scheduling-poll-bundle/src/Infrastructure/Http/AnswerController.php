<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Http;

use Phil\AccessLinks\LinkFormat;
use Phil\SchedulingPollBundle\Application\AnswerForm;
use Phil\SchedulingPollBundle\Application\Exception\AnswerRefused;
use Phil\SchedulingPollBundle\Application\Exception\NotFound;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Application\View\AnswerSheet;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The two pages of a respondent without an account: the poll's page, where anyone with its address joins by their
 * name, and their own page, opened by their personal link, where they answer until the poll closes.
 *
 * Both pages are sent with `Referrer-Policy: no-referrer`, so that a link out of them never carries the token,
 * `Cache-Control: private, no-store` and `X-Robots-Tag: noindex`. Opening a link changes nothing: only the form, posted,
 * writes. When the application has `symfony/security-csrf`, both forms carry a CSRF token and a post without the right
 * one is refused.
 */
final readonly class AnswerController
{
    public const string CSRF_ANSWER = 'phil_scheduling_poll_answer';

    public const string CSRF_JOIN = 'phil_scheduling_poll_join';

    public function __construct(
        private SchedulingPolls $polls,
        private SchedulingPollViews $views,
        private LinkFormat $links,
        private Environment $twig,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        private ?CsrfTokenManagerInterface $csrf = null,
    ) {
    }

    public function answer(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->post($request);
        }

        $token = $request->query->getString($this->links->parameter);
        $sheet = $this->views->sheet($token, $request->query->has('timezone') ? $request->query->getString('timezone') : null);

        return $this->page('@PhilSchedulingPoll/answer.html.twig', [
            'sheet' => $sheet,
            'token' => $token,
            'action' => $request->getPathInfo(),
            'csrf_token' => $this->csrf?->getToken(self::CSRF_ANSWER)->getValue(),
            'saved' => $request->query->getBoolean('saved'),
        ], $sheet instanceof AnswerSheet ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }

    public function join(Request $request, string $poll): Response
    {
        try {
            $found = $this->polls->get($poll);
        } catch (NotFound) {
            throw new NotFoundHttpException();
        }

        $errors = [];
        $values = ['name' => $request->request->getString('name'), 'email' => $request->request->getString('email')];

        if ($request->isMethod('POST')) {
            try {
                $this->checkCsrf($request, self::CSRF_JOIN);
                $issued = $this->polls->join($found->id, $values['name'], $values['email'], locale: $request->getLocale());

                return $this->secure(new RedirectResponse($issued->url->toString(), Response::HTTP_SEE_OTHER));
            } catch (InvalidSchedulingPoll|AnswerRefused $refused) {
                $errors[] = $refused->getMessage();
            }
        }

        return $this->page('@PhilSchedulingPoll/join.html.twig', [
            'poll' => $found,
            'slots' => $this->polls->slots($found->id),
            'open' => $found->isOpenAt($this->clock->now()),
            'action' => $request->getPathInfo(),
            'csrf_token' => $this->csrf?->getToken(self::CSRF_JOIN)->getValue(),
            'errors' => $errors,
            'values' => $values,
        ], [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function post(Request $request): Response
    {
        $token = $request->request->getString('token');
        $timezone = AnswerForm::timezone($request->request->all());

        try {
            $this->checkCsrf($request, self::CSRF_ANSWER);
            $this->polls->answer($token, AnswerForm::answers($request->request->all()), $timezone);

            return $this->secure(new RedirectResponse($this->links->for($token)->toString().'&saved=1', Response::HTTP_SEE_OTHER));
        } catch (InvalidSchedulingPoll|AnswerRefused $refused) {
            $sheet = $this->views->sheet($token, $timezone);

            return $this->page('@PhilSchedulingPoll/answer.html.twig', [
                'sheet' => $sheet,
                'token' => $token,
                'action' => $request->getPathInfo(),
                'csrf_token' => $this->csrf?->getToken(self::CSRF_ANSWER)->getValue(),
                'errors' => [$refused->getMessage()],
            ], $sheet instanceof AnswerSheet ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @throws InvalidSchedulingPoll when the CSRF token posted is not the right one
     */
    private function checkCsrf(Request $request, string $id): void
    {
        if ($this->csrf instanceof CsrfTokenManagerInterface && !$this->csrf->isTokenValid(new CsrfToken($id, $request->request->getString('_token')))) {
            throw InvalidSchedulingPoll::because($this->translator->trans('form.csrf', [], 'phil_scheduling_poll'));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function page(string $template, array $context, int $status): Response
    {
        return $this->secure(new Response($this->twig->render($template, $context), $status));
    }

    private function secure(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
