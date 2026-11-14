<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Twig\CentreView;
use Phil\NotificationsBundle\Infrastructure\Twig\WebPushComponent;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Current;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Tokens;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(WebPushComponent::class)]
final class WebPushComponentTest extends TestCase
{
    public function testTheButtonOfTheCurrentRecipient(): void
    {
        $current = new Current('42');
        $component = new WebPushComponent($this->view($current), 'KEY', $this->router(), new Packages(new Package(new StaticVersionStrategy('v1'))));

        self::assertTrue($component->isVisible());
        self::assertSame([
            'urls' => ['subscribe' => '/notifications/push/subscribe', 'unsubscribe' => '/notifications/push/unsubscribe'],
            'token' => 'good',
            'header' => 'X-Phil-Notifications-Token',
            'key' => 'KEY',
            'worker' => 'phil-notifications-worker/push-worker.js?v1',
        ], $component->getConfig());
        self::assertSame('/sw.js', new WebPushComponent($this->view($current), 'KEY', $this->router(), worker: '/sw.js')->getConfig()['worker']);
        self::assertSame('/sw.js', new WebPushComponent($this->view($current), 'KEY', $this->router(), new Packages(new Package(new StaticVersionStrategy('v1'))), '/sw.js')->getConfig()['worker']);

        $current->id = null;
        self::assertFalse($component->isVisible());
    }

    public function testWithoutRouterOrWorkerNothingShows(): void
    {
        $view = $this->view(new Current('42'));

        self::assertFalse(new WebPushComponent($view, 'KEY', packages: new Packages(new Package(new StaticVersionStrategy('v1'))))->isVisible());
        self::assertSame(['subscribe' => '', 'unsubscribe' => ''], new WebPushComponent($view, 'KEY')->getConfig()['urls']);
        self::assertFalse(new WebPushComponent($view, 'KEY', $this->router())->isVisible());
        self::assertNull(new WebPushComponent($view, 'KEY', $this->router())->getConfig()['worker']);
        self::assertTrue(new WebPushComponent($view, 'KEY', $this->router(), worker: '/sw.js')->isVisible());
    }

    private function view(Current $current): CentreView
    {
        $renderer = new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('comment.replied')), new Directory(), [new Subjects()]);

        return new CentreView(new InMemoryNotificationRepository(), $current, $renderer, new Preferences(), new CentreRequest(new Tokens()));
    }

    private function router(): UrlGenerator
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('push_routes.php');
        $routes->addPrefix('/notifications');

        return new UrlGenerator($routes, new RequestContext());
    }
}
