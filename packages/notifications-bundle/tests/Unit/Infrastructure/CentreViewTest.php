<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\FlashToasts;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Twig\BellComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\CentreView;
use Phil\NotificationsBundle\Infrastructure\Twig\ListComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\ToastsComponent;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Current;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingHub;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Tokens;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(CentreView::class)]
#[CoversClass(BellComponent::class)]
#[CoversClass(ListComponent::class)]
#[CoversClass(ToastsComponent::class)]
final class CentreViewTest extends TestCase
{
    private InMemoryNotificationRepository $repository;

    private Current $current;

    private Preferences $preferences;

    protected function setUp(): void
    {
        $this->repository = new InMemoryNotificationRepository();
        $this->current = new Current('42');
        $this->preferences = new Preferences();

        foreach (['a' => '10:00', 'b' => '11:00', 'c' => '12:00'] as $id => $time) {
            $this->repository->record(new Notification($id, 'comment.replied', '42', new SubjectReference('comment', $id), new DateTimeImmutable('2026-11-08 '.$time.' UTC'), '17'), false);
        }

        $this->repository->markRead('42', 'a', new DateTimeImmutable());
    }

    public function testTheCentreOfTheCurrentRecipient(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(locale: 'fr');
        $view = $this->view();

        self::assertSame('42', $view->recipient());
        self::assertSame(2, $view->unread());
        self::assertSame(['c', 'b'], array_map(static fn (RenderedNotification $n): string => $n->notification->id, $view->latest(2)));
        self::assertSame('Grace a répondu à comment c (fr)', $view->latest(1)[0]->title);
        self::assertSame('good', $view->token());
    }

    public function testAVisitorHasNoCentre(): void
    {
        $this->current->id = null;
        $view = $this->view();

        self::assertNull($view->recipient());
        self::assertSame(0, $view->unread());
        self::assertSame([], $view->latest(10));
        self::assertNull($view->config()['hub']);
    }

    public function testTheControllersConfiguration(): void
    {
        self::assertSame([
            'urls' => ['unread' => '/notifications/unread', 'readAll' => '/notifications/read-all'],
            'token' => 'good',
            'header' => 'X-Phil-Notifications-Token',
            'hub' => 'https://hub.test/.well-known/mercure?match=urn%3Aphil%3Anotifications%3A42',
        ], $this->view()->config());
    }

    public function testTheSubscriptionFollowsTheProtocolOfTheHub(): void
    {
        $legacy = $this->view(new RecordingHub('https://hub.test/mercure?auth=1', ProtocolVersion::Legacy), 'urn:x:');

        self::assertSame('https://hub.test/mercure?auth=1&topic=urn%3Ax%3A42', $legacy->config()['hub']);
    }

    public function testWithoutRouterTokenNorHubTheConfigurationIsEmpty(): void
    {
        $view = new CentreView($this->repository, $this->current, $this->renderer(), $this->preferences);

        self::assertSame(['urls' => null, 'token' => null, 'header' => 'X-Phil-Notifications-Token', 'hub' => null], $view->config());
        self::assertNull($view->token());
        self::assertSame([], $view->toasts());
    }

    public function testTheToastsOfTheSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->getFlashBag()->add(FlashToasts::FLASH_TYPE, ['id' => 'n1', 'title' => 'Hello']);
        $request = new Request();
        $request->setSession($session);

        $requests = new RequestStack([$request]);
        $view = new CentreView($this->repository, $this->current, $this->renderer(), $this->preferences, flashes: new FlashToasts($requests, $this->current));

        self::assertSame([['id' => 'n1', 'type' => '', 'title' => 'Hello', 'body' => '', 'url' => null, 'count' => 1]], new ToastsComponent($view)->getToasts());
    }

    public function testTheComponents(): void
    {
        $view = $this->view();
        $bell = new BellComponent($view);
        $list = new ListComponent($view);

        self::assertSame(10, $bell->limit);
        self::assertSame(20, $list->limit);
        $bell->limit = 1;
        $list->limit = 2;
        self::assertTrue($bell->isVisible());
        self::assertSame(2, $bell->getUnread());
        self::assertCount(1, $bell->getNotifications());
        self::assertCount(2, $list->getNotifications());
        self::assertSame('good', $bell->getToken());
        self::assertSame('good', $list->getToken());
        self::assertSame($view->config(), $bell->getConfig());

        $this->current->id = null;
        self::assertFalse($bell->isVisible());
    }

    private function view(?RecordingHub $hub = null, string $prefix = 'urn:phil:notifications:'): CentreView
    {
        $loader = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'));
        $routes = $loader->load('routes.php');
        $routes->addPrefix('/notifications');

        return new CentreView($this->repository, $this->current, $this->renderer(), $this->preferences, new CentreRequest(new Tokens()), new UrlGenerator($routes, new RequestContext()), $hub ?? new RecordingHub(), $prefix);
    }

    private function renderer(): NotificationRenderer
    {
        return new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('comment.replied')), new Directory(), [new Subjects()]);
    }
}
