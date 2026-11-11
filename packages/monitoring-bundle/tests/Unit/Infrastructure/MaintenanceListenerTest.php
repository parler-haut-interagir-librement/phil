<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Infrastructure\Http\MaintenanceListener;
use Phil\MonitoringBundle\Tests\Unit\Support\Grants;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryMaintenance;
use Phil\MonitoringBundle\Tests\Unit\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MaintenanceListener::class)]
final class MaintenanceListenerTest extends TestCase
{
    private InMemoryMaintenance $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryMaintenance(new MaintenanceState(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 600));
    }

    public function testAnOpenSiteIsLeftAlone(): void
    {
        $this->store->disable();

        self::assertNull($this->handle(new MaintenanceListener($this->store), Request::create('/')));
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $event = new RequestEvent($this->kernel(), Request::create('/'), HttpKernelInterface::SUB_REQUEST);
        new MaintenanceListener($this->store)($event);

        self::assertNull($event->getResponse());
    }

    public function testAVisitorGetsTheMaintenancePage(): void
    {
        $response = $this->handle(new MaintenanceListener($this->store, twig: Pages::twig()), Request::create('/'));

        self::assertNotNull($response);
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('600', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('Please come back in 10 minutes.', (string) $response->getContent());
    }

    public function testTheTemplateIsTheConfiguredOne(): void
    {
        $twig = new Environment(new ArrayLoader(['custom.html.twig' => 'Back in {{ maintenance.retryAfterSeconds }} s']));

        $response = $this->handle(new MaintenanceListener($this->store, twig: $twig, template: 'custom.html.twig'), Request::create('/'));

        self::assertSame('Back in 600 s', $response?->getContent());
    }

    public function testWithoutTwigTheAnswerIsPlainText(): void
    {
        $response = $this->handle(new MaintenanceListener($this->store), Request::create('/'));

        self::assertSame(503, $response?->getStatusCode());
        self::assertSame('The site is under maintenance. Please come back later.', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('600', $response->headers->get('Retry-After'));
    }

    public function testAClientThatPrefersJsonGetsJson(): void
    {
        $request = Request::create('/api/things');
        $request->headers->set('Accept', 'application/json');

        $response = $this->handle(new MaintenanceListener($this->store, twig: Pages::twig()), $request);

        self::assertSame(503, $response?->getStatusCode());
        self::assertSame('{"status":"maintenance","retry_after":600}', $response->getContent());
        self::assertSame('600', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testAnAllowedRouteIsServed(): void
    {
        $listener = new MaintenanceListener($this->store, allowedRoutes: ['phil_monitoring_ready']);
        $request = Request::create('/_health/ready');
        $request->attributes->set('_route', 'phil_monitoring_ready');

        $other = Request::create('/');
        $other->attributes->set('_route', 'home');

        self::assertNull($this->handle($listener, $request));
        self::assertNotNull($this->handle($listener, $other));
    }

    public function testAnAllowedAddressOrNetworkIsServed(): void
    {
        $listener = new MaintenanceListener($this->store, allowedIps: ['10.0.0.0/8', '2001:db8::1']);

        self::assertNull($this->handle($listener, Request::create('/', server: ['REMOTE_ADDR' => '10.1.2.3'])));
        self::assertNull($this->handle($listener, Request::create('/', server: ['REMOTE_ADDR' => '2001:db8::1'])));
        self::assertNotNull($this->handle($listener, Request::create('/', server: ['REMOTE_ADDR' => '192.168.1.1'])));
        self::assertNotNull($this->handle(new MaintenanceListener($this->store), Request::create('/', server: ['REMOTE_ADDR' => '10.1.2.3'])));

        $request = Request::create('/');
        $request->server->remove('REMOTE_ADDR');
        self::assertNotNull($this->handle($listener, $request));
    }

    public function testAUserGrantedAnAllowedRoleIsServed(): void
    {
        $grants = new Grants(['ROLE_OPS']);

        self::assertNull($this->handle(new MaintenanceListener($this->store, allowedRoles: ['ROLE_ADMIN', 'ROLE_OPS'], authorizationChecker: $grants), Request::create('/')));
        self::assertSame(['ROLE_ADMIN', 'ROLE_OPS'], $grants->asked);
        self::assertNotNull($this->handle(new MaintenanceListener($this->store, allowedRoles: ['ROLE_ADMIN'], authorizationChecker: $grants), Request::create('/')));
        self::assertNotNull($this->handle(new MaintenanceListener($this->store, allowedRoles: ['ROLE_OPS']), Request::create('/')));
    }

    public function testTheRolesAreAskedOnlyWhenNothingElseAllows(): void
    {
        $grants = new Grants(['ROLE_OPS']);
        $listener = new MaintenanceListener($this->store, ['127.0.0.1'], ['ROLE_OPS'], authorizationChecker: $grants);

        self::assertNull($this->handle($listener, Request::create('/')));
        self::assertSame([], $grants->asked);
    }

    private function handle(MaintenanceListener $listener, Request $request): ?Response
    {
        $event = new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);

        return $event->getResponse();
    }

    private function kernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
