<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Http;

use Closure;
use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Console\Fleet;
use Phil\SitesConsoleBundle\Application\Console\Journal;
use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Application\Message\DeliverConfigurationPush;
use Phil\SitesConsoleBundle\Application\Message\PullSite;
use Phil\SitesConsoleBundle\Application\Message\RunUpdatePlan;
use Phil\SitesConsoleBundle\Application\View\SiteOverview;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The screens of the console: the dashboard, a site, the versions side by side, the journal, the pushes and the
 * update plans. Reading takes the viewer role, acting the operator role; both come from the user's token, which the
 * application's firewall fills from its identity provider. Every action is a POST carrying a CSRF token, handed to
 * Messenger when a bus is there, run at once otherwise.
 */
final readonly class ConsoleController
{
    public const string CSRF_TOKEN_ID = 'phil_sites_console';

    public const int JOURNAL_LINES = 200;

    public function __construct(
        private Fleet $fleet,
        private Puller $puller,
        private ConfigurationPushes $pushes,
        private UpdatePlans $plans,
        private Journal $journal,
        private Environment $twig,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private string $viewerRole,
        private string $operatorRole,
        private string $layout,
        private ?TokenStorageInterface $tokens = null,
        private ?MessageBusInterface $bus = null,
    ) {
    }

    public function dashboard(): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('dashboard', ['overviews' => $this->fleet->overview()]);
    }

    public function site(string $id): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('site', ['overview' => $this->found(fn (): SiteOverview => $this->fleet->site($id)), 'journal' => $this->journal->recent(20, $id)]);
    }

    public function versions(): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('versions', ['matrix' => $this->fleet->matrix()]);
    }

    public function journal(): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('journal', ['entries' => $this->journal->recent(self::JOURNAL_LINES)]);
    }

    public function pushes(): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('pushes', ['pushes' => $this->pushes->recent(50)]);
    }

    public function push(string $id): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('push', ['push' => $this->found(fn (): ConfigurationPush => $this->pushes->get($id))]);
    }

    public function updates(): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('updates', ['plans' => $this->plans->recent(50)]);
    }

    public function update(string $id): Response
    {
        $this->allow($this->viewerRole);

        return $this->render('update', ['plan' => $this->found(fn (): UpdatePlan => $this->plans->get($id))]);
    }

    public function pull(Request $request, string $id): RedirectResponse
    {
        $this->act($request);
        $site = $this->found(fn (): SiteOverview => $this->fleet->site($id))->site;
        $this->dispatch(new PullSite($site->id()), fn (): mixed => $this->puller->pull($site->id()));

        return $this->back('phil_sites_console_site', ['id' => $site->id()]);
    }

    public function pullAll(Request $request): RedirectResponse
    {
        $this->act($request);

        foreach ($this->fleet->overview() as $overview) {
            $this->dispatch(new PullSite($overview->site->id()), fn (): mixed => $this->puller->pull($overview->site->id()));
        }

        return $this->back('phil_sites_console_dashboard');
    }

    public function deliver(Request $request, string $id): RedirectResponse
    {
        $this->act($request);
        $push = $this->found(fn (): ConfigurationPush => $this->pushes->get($id));
        $this->dispatch(new DeliverConfigurationPush($push->id(), $this->actor()), fn (): mixed => $this->pushes->deliver($push->id(), $this->actor()));

        return $this->back('phil_sites_console_push', ['id' => $push->id()]);
    }

    public function run(Request $request, string $id): RedirectResponse
    {
        $this->act($request);
        $plan = $this->found(fn (): UpdatePlan => $this->plans->get($id));
        $batches = $request->request->getInt('batches');
        $batches = $batches > 0 ? $batches : null;
        $this->dispatch(new RunUpdatePlan($plan->id(), $batches, $this->actor()), fn (): mixed => $this->plans->run($plan->id(), $batches, $this->actor()));

        return $this->back('phil_sites_console_update', ['id' => $plan->id()]);
    }

    private function allow(string $role): void
    {
        if (!$this->authorization->isGranted($role)) {
            throw new AccessDeniedException('The console is for its viewers and operators.');
        }
    }

    private function act(Request $request): void
    {
        $this->allow($this->operatorRole);

        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedException('The form has expired: reload the page.');
        }
    }

    /**
     * Hands the message to Messenger when a bus is there, or runs the work at once.
     *
     * @param Closure(): mixed $now
     */
    private function dispatch(object $message, Closure $now): void
    {
        if ($this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch($message);

            return;
        }

        $now();
    }

    private function actor(): ?string
    {
        return $this->tokens?->getToken()?->getUserIdentifier();
    }

    /**
     * @template T
     *
     * @param Closure(): T $find
     *
     * @return T
     */
    private function found(Closure $find): mixed
    {
        try {
            return $find();
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function back(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        $html = $this->twig->render('@PhilSitesConsole/'.$template.'.html.twig', $context + [
            'layout' => $this->layout,
            'can_operate' => $this->authorization->isGranted($this->operatorRole),
            'csrf_token' => $this->csrf->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]);

        return new Response($html, Response::HTTP_OK, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
