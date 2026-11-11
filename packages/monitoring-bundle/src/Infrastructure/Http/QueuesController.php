<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Http;

use Phil\MonitoringBundle\Application\Queue\Queues;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The on-screen view of the Messenger queues, for the users granted its role only: the messages each transport holds,
 * the failed messages of each failure transport, and the forms that retry or remove one — posted, with a CSRF token.
 * A user without the role meets the firewall, as with any denied page.
 */
final readonly class QueuesController
{
    public const string INDEX_ROUTE = 'phil_monitoring_queues';

    public const string RETRY_ROUTE = 'phil_monitoring_queues_retry';

    public const string REMOVE_ROUTE = 'phil_monitoring_queues_remove';

    public const string CSRF_TOKEN_ID = 'phil_monitoring_queues';

    public const string TRANSPORT = '[A-Za-z0-9][A-Za-z0-9_.-]{0,99}';

    public const string MESSAGE_ID = '[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}';

    public function __construct(
        private Queues $queues,
        private AuthorizationCheckerInterface $authorizationChecker,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $role,
        private int $failedLimit,
        private string $layout,
    ) {
    }

    public function index(): Response
    {
        $this->denyUnlessGranted();
        $failed = [];

        foreach ($this->queues->failureTransports() as $transport) {
            $failed[$transport] = $this->queues->failed($transport, $this->failedLimit);
        }

        return new Response($this->twig->render('@PhilMonitoring/queues.html.twig', [
            'layout' => $this->layout,
            'queues' => $this->queues->overview(),
            'failed' => $failed,
            'csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function retry(Request $request, string $transport, string $id): RedirectResponse
    {
        $this->guard($request, $transport);

        return $this->done($this->queues->retry($transport, $id));
    }

    public function remove(Request $request, string $transport, string $id): RedirectResponse
    {
        $this->guard($request, $transport);

        return $this->done($this->queues->remove($transport, $id));
    }

    private function guard(Request $request, string $transport): void
    {
        $this->denyUnlessGranted();

        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }

        if (!$this->queues->isFailureTransport($transport)) {
            throw new NotFoundHttpException('No such failure transport.');
        }
    }

    private function done(bool $found): RedirectResponse
    {
        if (!$found) {
            throw new NotFoundHttpException('No such failed message.');
        }

        return new RedirectResponse($this->urls->generate(self::INDEX_ROUTE), Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessGranted(): void
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$this->role]);

            throw $exception;
        }
    }
}
