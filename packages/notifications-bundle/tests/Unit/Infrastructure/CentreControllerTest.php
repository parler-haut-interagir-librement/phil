<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Phil\NotificationsBundle\Infrastructure\Http\CentreController;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Current;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CentreController::class)]
#[CoversClass(CentreRequest::class)]
#[CoversClass(Routes::class)]
final class CentreControllerTest extends TestCase
{
    private InMemoryNotificationRepository $repository;

    private Current $current;

    protected function setUp(): void
    {
        $this->repository = new InMemoryNotificationRepository();
        $this->current = new Current('42');

        foreach (['a', 'b'] as $id) {
            $this->repository->record(new Notification($id, 'welcome', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), false);
        }

        $this->repository->record(new Notification('theirs', 'welcome', '43', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), false);
    }

    public function testTheUnreadCountOfTheCurrentRecipient(): void
    {
        $response = $this->controller()->unread();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"unread":2}', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testAVisitorIsRefused(): void
    {
        $this->current->id = null;

        self::assertSame(403, $this->controller()->unread()->getStatusCode());
        self::assertSame('{"error":"forbidden"}', $this->controller()->unread()->getContent());
        self::assertSame(403, $this->controller()->readAll($this->request())->getStatusCode());
        self::assertSame('{"error":"forbidden"}', $this->controller()->readAll($this->request())->getContent());
    }

    public function testANotificationIsMarkedReadAndTheControllerGetsTheCount(): void
    {
        $response = $this->controller()->read($this->request(json: true), 'a');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"unread":1}', $response->getContent());
        self::assertSame(NotificationState::Read, $this->repository->find('42', 'a')?->state);
        self::assertSame('2026-11-08 12:00:00', $this->repository->find('42', 'a')->readAt?->format('Y-m-d H:i:s'));
    }

    public function testAReadNotificationMarkedAgainIsStillFound(): void
    {
        $this->controller()->read($this->request(json: true), 'a');

        self::assertSame(200, $this->controller()->read($this->request(json: true), 'a')->getStatusCode());
    }

    public function testAFormComesBackToItsPage(): void
    {
        $response = $this->controller()->read($this->request(referer: 'https://site.test/events/7'), 'a');

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('https://site.test/events/7', $response->headers->get('Location'));
    }

    public function testAFormFromAnotherSiteGoesHome(): void
    {
        foreach (['https://evil.test/page', 'https://site.test.evil.test/page', 'https://site.test', '', null] as $referer) {
            self::assertSame('/', $this->controller()->readAll($this->request(referer: $referer))->headers->get('Location'));
        }
    }

    public function testAnotherRecipientsNotificationIsNotFound(): void
    {
        $response = $this->controller()->read($this->request(json: true), 'theirs');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"not_found"}', $response->getContent());
        self::assertSame(NotificationState::Unread, $this->repository->find('43', 'theirs')?->state);
        self::assertSame(404, $this->controller()->archive($this->request(json: true), 'theirs')->getStatusCode());
        self::assertSame(NotificationState::Unread, $this->repository->find('43', 'theirs')?->state);
    }

    public function testAllAreMarkedRead(): void
    {
        $response = $this->controller()->readAll($this->request(json: true));

        self::assertSame('{"unread":0}', $response->getContent());
        self::assertSame(1, $this->repository->unreadCount('43'));
    }

    public function testANotificationIsArchived(): void
    {
        $response = $this->controller()->archive($this->request(json: true), 'b');

        self::assertSame('{"unread":1}', $response->getContent());
        self::assertSame(NotificationState::Archived, $this->repository->find('42', 'b')?->state);
    }

    public function testAChangeWithoutTheTokenIsRefused(): void
    {
        foreach ([$this->request(token: null), $this->request(token: 'bad'), $this->request(crossSite: true)] as $request) {
            self::assertSame(403, $this->controller()->read($request, 'a')->getStatusCode());
        }

        self::assertSame(2, $this->repository->unreadCount('42'));
    }

    public function testTheTokenMayComeInTheHeader(): void
    {
        $request = $this->request(token: null, json: true);
        $request->headers->set(CentreRequest::HEADER, 'good');
        $request->request->set(CentreRequest::FIELD, 'bad');

        self::assertSame(200, $this->controller()->read($request, 'a')->getStatusCode());
    }

    public function testTheTokenIsGivenToForms(): void
    {
        self::assertSame('good', new CentreRequest(new Tokens())->token());
        self::assertTrue(new CentreRequest(new Tokens())->isTrusted($this->request(sameSite: true)));
    }

    public function testTheRouteNames(): void
    {
        self::assertSame(['unread' => 'phil_notifications_unread', 'read' => 'phil_notifications_read', 'readAll' => 'phil_notifications_read_all', 'archive' => 'phil_notifications_archive'], Routes::NAMES);
        self::assertSame('phil_notifications_remote', Routes::REMOTE);
    }

    private function controller(): CentreController
    {
        return new CentreController($this->repository, $this->current, new CentreRequest(new Tokens()), new MockClock('2026-11-08 12:00:00 UTC'));
    }

    private function request(?string $token = 'good', bool $json = false, ?string $referer = null, bool $crossSite = false, bool $sameSite = false): Request
    {
        $request = Request::create('https://site.test/notifications/a/read', 'POST', null === $token ? [] : [CentreRequest::FIELD => $token]);

        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        if (null !== $referer) {
            $request->headers->set('Referer', $referer);
        }

        if ($crossSite) {
            $request->headers->set('Sec-Fetch-Site', 'cross-site');
        }

        if ($sameSite) {
            $request->headers->set('Sec-Fetch-Site', 'same-origin');
        }

        return $request;
    }
}
