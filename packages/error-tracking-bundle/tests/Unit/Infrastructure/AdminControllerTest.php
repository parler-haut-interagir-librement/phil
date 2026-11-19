<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin\ErrorTrackingAdmin;
use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\FixedCsrfTokens;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RoleChecker;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(AdminController::class)]
#[CoversClass(ErrorTrackingAdmin::class)]
final class AdminControllerTest extends TestCase
{
    private Tracking $tracking;

    protected function setUp(): void
    {
        $this->tracking = new Tracking();
    }

    public function testTheListOfTheGroups(): void
    {
        $this->capture('Export of order 12 failed', 'https://example.com/orders?page=2');
        $response = $this->controller()->index(Request::create('/admin/errors'));
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('RuntimeException: Export of order 12 failed', $html);
        self::assertStringContainsString('1 group(s).', $html);
        self::assertStringContainsString('<td>open</td>', $html);
        self::assertStringContainsString('href="/admin/errors/'.array_key_first($this->tracking->store->groups).'"', $html);
        self::assertStringNotContainsString('rel="next"', $html);
    }

    public function testEveryPageTellsTheAlertsHaveNoDestination(): void
    {
        $fingerprint = $this->capture('Export failed', 'https://example.com/orders');
        $notice = '<p role="status"><strong>No alert is sent.</strong> '.AlertDestination::None->describe().'</p>';

        self::assertStringContainsString($notice, (string) $this->controller(alerts: AlertDestination::None)->index(Request::create('/admin/errors'))->getContent());
        self::assertStringContainsString($notice, (string) $this->controller(alerts: AlertDestination::None)->show(Request::create('/'), $fingerprint)->getContent());
    }

    #[TestWith([AlertDestination::Chat])]
    #[TestWith([AlertDestination::Notification])]
    #[TestWith([AlertDestination::Disabled])]
    public function testAPageSaysNothingOfAlertsThatHaveADestinationOrAreOff(AlertDestination $alerts): void
    {
        $fingerprint = $this->capture('Export failed', 'https://example.com/orders');

        self::assertStringNotContainsString('No alert is sent.', (string) $this->controller(alerts: $alerts)->index(Request::create('/admin/errors'))->getContent());
        self::assertStringNotContainsString('No alert is sent.', (string) $this->controller(alerts: $alerts)->show(Request::create('/'), $fingerprint)->getContent());
    }

    public function testTheListIsFilteredAndPaged(): void
    {
        for ($index = 0; $index < 5; ++$index) {
            $this->tracking->store->save(new ErrorGroup(str_repeat((string) $index, 64), 'E'.$index, null, 0 === $index ? GroupStatus::Resolved : GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable()));
        }

        $html = (string) $this->controller(pageSize: 2)->index(Request::create('/admin/errors?page=2'))->getContent();
        self::assertStringContainsString('<a href="/admin/errors/'.str_repeat('2', 64).'">E2</a>', $html);
        self::assertStringContainsString('<a href="/admin/errors/'.str_repeat('3', 64).'">E3</a>', $html);
        self::assertStringNotContainsString('>E1<', $html);
        self::assertStringContainsString('Page 2 of 3', $html);
        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString('rel="next"', $html);

        $html = (string) $this->controller(pageSize: 2)->index(Request::create('/admin/errors?status=resolved&page=0'))->getContent();
        self::assertStringContainsString('>E0<', $html);
        self::assertStringNotContainsString('>E1<', $html);
        self::assertStringContainsString('1 group(s).', $html);
        self::assertStringNotContainsString('Page 1', $html);

        self::assertStringContainsString('Page 3 of 3', (string) $this->controller(pageSize: 2)->index(Request::create('/admin/errors?page=3'))->getContent());
        $first = (string) $this->controller(pageSize: 2)->index(Request::create('/admin/errors?page=0'))->getContent();
        self::assertStringContainsString('Page 1 of 3', $first);
        self::assertStringContainsString('>E0<', $first);
        self::assertStringContainsString('Page 1 of 3', (string) $this->controller(pageSize: 2)->index(Request::create('/admin/errors'))->getContent());
        self::assertStringContainsString('Page 2 of 2', (string) $this->controller(pageSize: 4)->index(Request::create('/admin/errors?page=2'))->getContent());
        self::assertStringContainsString('Page 1 of 5', (string) $this->controller(pageSize: 1)->index(Request::create('/admin/errors?status=bogus'))->getContent());
        self::assertStringContainsString('No error.', (string) $this->controller()->index(Request::create('/admin/errors?status=ignored'))->getContent());
    }

    public function testTheDetailOfAGroup(): void
    {
        $fingerprint = $this->capture('Export failed', 'https://example.com/orders?token=abc');
        $this->tracking->capture->throwable(new RuntimeException('Export failed'), new CaptureContext(Source::Console, context: ['command' => 'app:export', 'dry' => false, 'retry' => true]));
        $html = (string) $this->controller(eventsShown: 1)->show(Request::create('/'), $fingerprint)->getContent();

        self::assertStringContainsString('<h1>RuntimeException: Export failed</h1>', $html);
        self::assertStringContainsString('console', $html);
        self::assertStringContainsString('<td>app:export</td>', $html);
        self::assertStringContainsString('<td>false</td>', $html);
        self::assertStringContainsString('<td>true</td>', $html);
        self::assertStringNotContainsString('token=abc', $html);
        self::assertStringContainsString('value="token-phil_error_tracking"', $html);
        self::assertStringContainsString('action="/admin/errors/'.$fingerprint.'/resolve"', $html);

        $both = (string) $this->controller()->show(Request::create('/'), $fingerprint)->getContent();
        self::assertStringContainsString('GET https://example.com/orders?token=[Filtered]', $both);
    }

    public function testAnUnknownGroupIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->show(Request::create('/'), str_repeat('0', 64));
    }

    #[TestWith(['resolve', GroupStatus::Resolved])]
    #[TestWith(['ignore', GroupStatus::Ignored])]
    #[TestWith(['reopen', GroupStatus::Open])]
    public function testTheStatusOfAGroupIsChanged(string $action, GroupStatus $status): void
    {
        $fingerprint = $this->capture('Export failed');
        $this->tracking->store->save($this->tracking->store->groups[$fingerprint]->ignore());
        $response = $this->controller()->status(Request::create('/x', 'POST', ['_token' => 'token-phil_error_tracking']), $fingerprint, $action);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/errors/'.$fingerprint, $response->getTargetUrl());
        self::assertSame($status, $this->tracking->store->groups[$fingerprint]->status);
    }

    public function testAnUnknownActionIsNotFound(): void
    {
        $fingerprint = $this->capture('Export failed');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No such action.');

        $this->controller()->status(Request::create('/x', 'POST', ['_token' => 'token-phil_error_tracking']), $fingerprint, 'delete');
    }

    public function testABadTokenIsRefused(): void
    {
        $fingerprint = $this->capture('Export failed');

        try {
            $this->controller()->status(Request::create('/x', 'POST', ['_token' => 'forged']), $fingerprint, 'resolve');
            self::fail('A forged token was accepted.');
        } catch (BadRequestHttpException) {
            self::assertSame(GroupStatus::Open, $this->tracking->store->groups[$fingerprint]->status);
        }
    }

    public function testAUserWithoutTheRoleIsDenied(): void
    {
        $controller = $this->controller(['ROLE_USER']);

        foreach ([
            static fn (): Response => $controller->index(Request::create('/')),
            static fn (): Response => $controller->show(Request::create('/'), str_repeat('0', 64)),
            static fn (): RedirectResponse => $controller->status(Request::create('/', 'POST'), str_repeat('0', 64), 'resolve'),
        ] as $call) {
            try {
                $call();
                self::fail('Access was granted.');
            } catch (AccessDeniedException $denied) {
                self::assertSame(['ROLE_ERRORS'], $denied->getAttributes());
            }
        }
    }

    public function testInABackOfficeThePagesUseTheDashboardsLayoutAndRoutes(): void
    {
        $fingerprint = $this->capture('Export failed', 'https://example.com/orders');
        $controller = $this->controller(alerts: AlertDestination::None);

        $list = (string) $controller->index($this->inBackOffice('/admin/errors/'))->getContent();
        self::assertStringStartsWith('<ea-page><h1>Errors</h1>', $list, 'the title fills the content title of EasyAdmin');
        self::assertStringContainsString('<ea-main>', $list);
        self::assertStringContainsString('No alert is sent.', $list);
        self::assertStringContainsString('href="/back/admin/errors/'.$fingerprint.'"', $list);
        self::assertStringNotContainsString('<!DOCTYPE html>', $list, 'not the layout of the bundle');

        $detail = (string) $controller->show($this->inBackOffice('/admin/errors/'.$fingerprint), $fingerprint)->getContent();
        self::assertStringContainsString('<h1>RuntimeException: Export failed</h1>', $detail);
        self::assertStringContainsString('<a href="/back/admin/errors/">All errors</a>', $detail);
        self::assertStringContainsString('action="/back/admin/errors/'.$fingerprint.'/resolve"', $detail);

        $response = $controller->status($this->inBackOffice('/x', ['_token' => 'token-phil_error_tracking']), $fingerprint, 'resolve');
        self::assertSame('/back/admin/errors/'.$fingerprint, $response->getTargetUrl());

        $outside = new Request();
        $outside->attributes->set(ErrorTrackingAdmin::CONTEXT_ATTRIBUTE, 'admin');
        self::assertStringContainsString('<!DOCTYPE html>', (string) $controller->index($outside)->getContent(), 'only a context of EasyAdmin counts');
    }

    public function testTheMenuItemLinksToTheListUnderTheDashboard(): void
    {
        $item = ErrorTrackingAdmin::menuItem('back')->getAsDto();
        self::assertSame('Errors', $item->getLabel());
        self::assertSame('fa fa-bug', $item->getIcon());
        self::assertSame('back_errors_groups', $item->getRouteName());

        $item = ErrorTrackingAdmin::menuItem(label: 'Bugs', icon: null)->getAsDto();
        self::assertSame('Bugs', $item->getLabel());
        self::assertNull($item->getIcon());
        self::assertSame('admin_errors_groups', $item->getRouteName());
    }

    public function testTheRouteConstants(): void
    {
        self::assertSame(['phil_error_tracking_groups', 'phil_error_tracking_group', 'phil_error_tracking_group_status', 'phil_error_tracking'], [AdminController::INDEX_ROUTE, AdminController::SHOW_ROUTE, AdminController::STATUS_ROUTE, AdminController::CSRF_TOKEN_ID]);
        self::assertMatchesRegularExpression('/^(?:'.AdminController::FINGERPRINT.')$/D', str_repeat('a', 64));
        self::assertMatchesRegularExpression('/^(?:'.AdminController::ACTION.')$/D', 'reopen');
    }

    /**
     * A request routed through the dashboard `admin`, with EasyAdmin's context.
     *
     * @param array<string, string> $parameters
     */
    private function inBackOffice(string $uri, array $parameters = []): Request
    {
        $context = self::createStub(AdminContextInterface::class);
        $context->method('getDashboardRouteName')->willReturn('admin');
        $request = Request::create($uri, [] === $parameters ? 'GET' : 'POST', $parameters);
        $request->attributes->set(ErrorTrackingAdmin::CONTEXT_ATTRIBUTE, $context);

        return $request;
    }

    private function capture(string $message, ?string $url = null): string
    {
        $request = null === $url ? null : new RequestSummary('GET', $url);
        $capture = $this->tracking->capture->throwable(new RuntimeException($message), new CaptureContext(Source::Http, request: $request));

        return $capture->fingerprint ?? '';
    }

    /**
     * @param list<string> $roles
     */
    private function controller(array $roles = ['ROLE_ERRORS'], int $pageSize = 50, int $eventsShown = 10, AlertDestination $alerts = AlertDestination::Chat): AdminController
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/admin/errors');

        // The routes EasyAdmin grafts on the dashboard `admin`, which a back office would serve under its own path.
        foreach (ErrorTrackingAdmin::ROUTES as $name => $suffix) {
            $route = clone ($routes->get($name) ?? throw new RuntimeException('No route '.$name));
            $routes->add('admin'.$suffix, $route->setPath('/back'.$route->getPath()));
        }

        $urls = new UrlGenerator($routes, new RequestContext());
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilErrorTracking');
        $loader->addPath(\dirname(__DIR__).'/Fixture/easyadmin', 'EasyAdmin');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addExtension(new RoutingExtension($urls));

        return new AdminController($this->tracking->store, $this->tracking->store, new RoleChecker($roles), new FixedCsrfTokens(), $twig, $urls, $alerts, 'ROLE_ERRORS', '@PhilErrorTracking/layout.html.twig', $pageSize, $eventsShown);
    }
}
