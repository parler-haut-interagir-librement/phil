<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\MonitoringBundle\Application\Queue\Queues;
use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Phil\MonitoringBundle\Tests\Unit\Support\FakeReceiver;
use Phil\MonitoringBundle\Tests\Unit\Support\FixedCsrfTokens;
use Phil\MonitoringBundle\Tests\Unit\Support\Grants;
use Phil\MonitoringBundle\Tests\Unit\Support\Pages;
use Phil\MonitoringBundle\Tests\Unit\Support\PlainReceiver;
use Phil\MonitoringBundle\Tests\Unit\Support\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

#[CoversClass(QueuesController::class)]
final class QueuesControllerTest extends TestCase
{
    private FakeReceiver $failed;

    private RecordingBus $bus;

    protected function setUp(): void
    {
        $this->failed = new FakeReceiver([
            new Envelope(new stdClass(), [new TransportMessageIdStamp(7), new SentToFailureTransportStamp('async'), new RedeliveryStamp(2, new DateTimeImmutable('2026-11-08 09:30:00 UTC')), new ErrorDetailsStamp(LogicException::class, 0, 'jane@example.com')]),
            new Envelope(new stdClass(), [new TransportMessageIdStamp(8)]),
        ]);
        $this->bus = new RecordingBus();
    }

    public function testThePageListsTheQueuesAndTheFailedMessagesWithoutTheirContent(): void
    {
        $response = $this->controller(['ROLE_ADMIN'], limit: 1)->index();
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<td>async</td>', $html);
        self::assertStringContainsString('<td>not counted</td>', $html);
        self::assertStringContainsString('<td>failed (failure)</td>', $html);
        self::assertStringContainsString('<td>2</td>', $html);
        self::assertStringContainsString('Failed messages in failed', $html);
        self::assertStringContainsString('<td>stdClass</td>', $html);
        self::assertStringContainsString('<td>LogicException</td>', $html);
        self::assertStringContainsString('<td>2026-11-08T09:30:00+00:00</td>', $html);
        self::assertStringContainsString('action="/admin/queues/failed/7/retry"', $html);
        self::assertStringContainsString('action="/admin/queues/failed/7/remove"', $html);
        self::assertStringContainsString('value="phil_monitoring_queues-token"', $html);
        self::assertStringNotContainsString('/failed/8/', $html);
        self::assertStringNotContainsString('jane@example.com', $html);
        self::assertSame([1], $this->failed->limits);
    }

    public function testThePageExtendsTheConfiguredLayout(): void
    {
        $twig = new Environment(new ChainLoader([new ArrayLoader(['custom.html.twig' => '[{% block title %}{% endblock %}]']), Pages::twig()->getLoader()]));
        $twig->addExtension(new RoutingExtension(Pages::urlGenerator()));

        $response = $this->controller(['ROLE_ADMIN'], twig: $twig, layout: 'custom.html.twig')->index();

        self::assertSame('[Messenger queues]', $response->getContent());
    }

    public function testThePageWithoutTransports(): void
    {
        $controller = new QueuesController(new Queues(new ServiceLocator([]), [], [], $this->bus), new Grants(['ROLE_ADMIN']), new FixedCsrfTokens(), Pages::twig(), Pages::urlGenerator(), 'ROLE_ADMIN', 50, '@PhilMonitoring/layout.html.twig');

        self::assertStringContainsString('No transport.', (string) $controller->index()->getContent());
    }

    public function testAFailureTransportWithoutFailedMessages(): void
    {
        $this->failed->envelopes = [];

        self::assertStringContainsString('<p>None.</p>', (string) $this->controller(['ROLE_ADMIN'])->index()->getContent());
    }

    public function testThePageAndItsActionsNeedTheRole(): void
    {
        $grants = new Grants(['ROLE_USER']);
        $controller = $this->controller(grants: $grants, role: 'ROLE_OPS');

        foreach ([$controller->index(...), fn (): RedirectResponse => $controller->retry($this->post('phil_monitoring_queues-token'), 'failed', '7'), fn (): RedirectResponse => $controller->remove($this->post('phil_monitoring_queues-token'), 'failed', '7')] as $action) {
            try {
                $action();
                self::fail('A user without the role was served.');
            } catch (AccessDeniedException $exception) {
                self::assertSame(['ROLE_OPS'], $exception->getAttributes());
            }
        }

        self::assertSame(['ROLE_OPS', 'ROLE_OPS', 'ROLE_OPS'], $grants->asked);
        self::assertSame([], $this->failed->rejected);
    }

    public function testARetryGoesBackToItsTransport(): void
    {
        $response = $this->controller(['ROLE_ADMIN'])->retry($this->post('phil_monitoring_queues-token'), 'failed', '7');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/queues/', $response->getTargetUrl());
        self::assertCount(1, $this->bus->dispatched);
        self::assertCount(1, $this->failed->rejected);
    }

    public function testARemovalLeavesTheFailureTransport(): void
    {
        $response = $this->controller(['ROLE_ADMIN'])->remove($this->post('phil_monitoring_queues-token'), 'failed', '8');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/queues/', $response->getTargetUrl());
        self::assertSame([], $this->bus->dispatched);
        self::assertCount(1, $this->failed->rejected);
    }

    public function testAnActionNeedsTheCsrfToken(): void
    {
        $controller = $this->controller(['ROLE_ADMIN']);

        foreach ([new Request(), $this->post('wrong')] as $request) {
            foreach ([$controller->retry(...), $controller->remove(...)] as $action) {
                try {
                    $action($request, 'failed', '7');
                    self::fail('An action ran without its CSRF token.');
                } catch (BadRequestHttpException $exception) {
                    self::assertSame('The CSRF token is not valid.', $exception->getMessage());
                }
            }
        }

        self::assertSame([], $this->failed->rejected);
    }

    public function testAnActionOnAnotherTransportOrMessageIsNotFound(): void
    {
        $controller = $this->controller(['ROLE_ADMIN']);

        foreach ([['async', '7', 'No such failure transport.'], ['failed', '9', 'No such failed message.']] as [$transport, $id, $message]) {
            foreach ([$controller->retry(...), $controller->remove(...)] as $action) {
                try {
                    $action($this->post('phil_monitoring_queues-token'), $transport, $id);
                    self::fail('An action ran on a message that is not there.');
                } catch (NotFoundHttpException $exception) {
                    self::assertSame($message, $exception->getMessage());
                }
            }
        }

        self::assertSame([], $this->failed->rejected);
    }

    /**
     * @param list<string> $granted
     */
    private function controller(array $granted = [], ?Grants $grants = null, string $role = 'ROLE_ADMIN', int $limit = 50, ?Environment $twig = null, string $layout = '@PhilMonitoring/layout.html.twig'): QueuesController
    {
        $receivers = new ServiceLocator([
            'async' => static fn (): ReceiverInterface => new PlainReceiver(),
            'failed' => fn (): ReceiverInterface => $this->failed,
        ]);

        return new QueuesController(new Queues($receivers, ['async', 'failed'], ['failed'], $this->bus), $grants ?? new Grants($granted), new FixedCsrfTokens(), $twig ?? Pages::twig(), Pages::urlGenerator(), $role, $limit, $layout);
    }

    private function post(string $token): Request
    {
        return Request::create('/', 'POST', ['_token' => $token]);
    }
}
