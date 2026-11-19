<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Http;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin\ErrorTrackingAdmin;
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
 * The administration pages of the groups, for the users granted their role only: the list of the groups by status,
 * seen last first, a page per group with its latest events and their traces, and the forms that resolve, ignore or
 * reopen a group — posted, with a CSRF token. Every page says when the alerts have no destination, the degraded
 * state. A user without the role meets the firewall, as with any denied page.
 *
 * The pages answer on the bundle's routes, which the application imports, and, when EasyAdminBundle is registered, on
 * the routes `#[AdminRoute]` grafts on each dashboard: there they render in `ErrorTrackingAdmin::LAYOUT`, and their
 * links and the redirection after a form go to the routes of the same dashboard.
 */
#[AdminRoute(path: '/errors', name: 'errors')]
final readonly class AdminController
{
    public const string INDEX_ROUTE = 'phil_error_tracking_groups';

    public const string SHOW_ROUTE = 'phil_error_tracking_group';

    public const string STATUS_ROUTE = 'phil_error_tracking_group_status';

    public const string CSRF_TOKEN_ID = 'phil_error_tracking';

    public const string FINGERPRINT = '[0-9a-f]{64}';

    public const string ACTION = 'resolve|ignore|reopen';

    public function __construct(
        private ErrorGroupReader $reader,
        private ErrorStore $store,
        private AuthorizationCheckerInterface $authorizationChecker,
        private CsrfTokenManagerInterface $csrfTokens,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private AlertDestination $alerts,
        private string $role,
        private string $layout,
        private int $pageSize,
        private int $eventsShown,
    ) {
    }

    #[AdminRoute(path: '/', name: 'groups', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        $this->denyUnlessGranted();
        $status = GroupStatus::tryFrom($request->query->getString('status'));
        $page = max(1, $request->query->getInt('page'));
        $total = $this->reader->countGroups($status);

        return $this->render($request, '@PhilErrorTracking/groups.html.twig', [
            'groups' => $this->reader->groups($status, $this->pageSize, ($page - 1) * $this->pageSize),
            'status' => $status,
            'statuses' => GroupStatus::cases(),
            'page' => $page,
            'pages' => intdiv($total + $this->pageSize - 1, $this->pageSize),
            'total' => $total,
        ]);
    }

    #[AdminRoute(path: '/{fingerprint}', name: 'group', options: ['requirements' => ['fingerprint' => self::FINGERPRINT], 'methods' => ['GET']])]
    public function show(Request $request, string $fingerprint): Response
    {
        $this->denyUnlessGranted();

        return $this->render($request, '@PhilErrorTracking/group.html.twig', [
            'group' => $this->group($fingerprint),
            'events' => $this->reader->events($fingerprint, $this->eventsShown),
        ]);
    }

    #[AdminRoute(path: '/{fingerprint}/{action}', name: 'group_status', options: ['requirements' => ['fingerprint' => self::FINGERPRINT, 'action' => self::ACTION], 'methods' => ['POST']])]
    public function status(Request $request, string $fingerprint, string $action): RedirectResponse
    {
        $this->denyUnlessGranted();

        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_token')))) {
            throw new BadRequestHttpException('The CSRF token is not valid.');
        }

        $group = $this->group($fingerprint);
        $this->store->save(match ($action) {
            'resolve' => $group->resolve(),
            'ignore' => $group->ignore(),
            'reopen' => $group->reopen(),
            default => throw new NotFoundHttpException('No such action.'),
        });

        return new RedirectResponse($this->urls->generate($this->routes($request)['group'], ['fingerprint' => $fingerprint]), Response::HTTP_SEE_OTHER);
    }

    private function group(string $fingerprint): ErrorGroup
    {
        return $this->store->find($fingerprint) ?? throw new NotFoundHttpException('No such group.');
    }

    /**
     * The routes of the pages, by their part: the bundle's, or those of the dashboard the request came through.
     *
     * @return array{groups: string, group: string, status: string}
     */
    private function routes(Request $request): array
    {
        $dashboard = ErrorTrackingAdmin::dashboardRoute($request);

        if (null === $dashboard) {
            return ['groups' => self::INDEX_ROUTE, 'group' => self::SHOW_ROUTE, 'status' => self::STATUS_ROUTE];
        }

        return [
            'groups' => $dashboard.ErrorTrackingAdmin::ROUTES[self::INDEX_ROUTE],
            'group' => $dashboard.ErrorTrackingAdmin::ROUTES[self::SHOW_ROUTE],
            'status' => $dashboard.ErrorTrackingAdmin::ROUTES[self::STATUS_ROUTE],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(Request $request, string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => null === ErrorTrackingAdmin::dashboardRoute($request) ? $this->layout : ErrorTrackingAdmin::LAYOUT,
            'routes' => $this->routes($request),
            'alerts' => $this->alerts,
            'csrf_token' => $this->csrfTokens->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
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
