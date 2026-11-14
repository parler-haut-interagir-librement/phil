<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit;

use Minishlink\WebPush\WebPush;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Infrastructure\Console\VapidKeysCommand;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsServices;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationSchemaListener;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\PushSubscriptionController;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Twig\CentreView;
use Phil\NotificationsBundle\Infrastructure\Twig\WebPushComponent;
use Phil\NotificationsBundle\Infrastructure\WebPush\MinishlinkWebPushSender;
use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

#[CoversClass(PhilNotificationsBundle::class)]
#[CoversClass(NotificationsServices::class)]
final class WebPushServicesTest extends TestCase
{
    private const array ON = ['enabled' => true, 'subject' => 'mailto:ops@example.com'];

    public function testWebPushIsOffByDefault(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->has(PushSubscriptions::class));
        self::assertFalse($container->hasDefinition(PushSubscriber::class));
        self::assertFalse($container->hasDefinition(PushSubscriptionController::class));
        self::assertFalse($container->hasDefinition(WebPushComponent::class));
        self::assertFalse($container->hasDefinition(MinishlinkWebPushSender::class));
        self::assertNull($container->getDefinition(NotificationDispatcher::class)->getArgument(8));
        self::assertSame([['command' => 'phil:notifications:vapid-keys', 'description' => 'Writes a new pair of VAPID keys for Web Push.']], $container->getDefinition(VapidKeysCommand::class)->getTag('console.command'));
    }

    public function testTheBundlesWebPush(): void
    {
        $container = $this->load([['web_push' => self::ON]]);

        self::assertSame(DbalPushSubscriptions::class, (string) $container->getAlias(PushSubscriptions::class));
        self::assertTrue($container->getAlias(PushSubscriptions::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference('clock'), 10], $container->getDefinition(DbalPushSubscriptions::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), false, true], $container->getDefinition(NotificationSchemaListener::class)->getArguments());
        self::assertEquals([new Reference(PushSubscriptions::class), PushSubscriber::PUSH_SERVICES], $container->getDefinition(PushSubscriber::class)->getArguments());
        self::assertTrue($container->getDefinition(PushSubscriber::class)->isPublic());

        $controller = $container->getDefinition(PushSubscriptionController::class);
        self::assertEquals([new Reference(PushSubscriber::class), new Reference(CurrentRecipient::class), new Reference(CentreRequest::class)], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());

        $component = $container->getDefinition(WebPushComponent::class);
        self::assertEquals([
            new Reference(CentreView::class),
            '%env(PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY)%',
            new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
        ], $component->getArguments());
        self::assertTrue($component->isAutoconfigured());

        self::assertSame(['mailto:ops@example.com', '%env(PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY)%', '%env(PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY)%'], $container->getDefinition(VapidKeys::class)->getArguments());

        $sender = $container->getDefinition(MinishlinkWebPushSender::class);
        self::assertTrue($sender->isPublic());
        self::assertEquals(new Reference(NotificationTypes::class), $sender->getArgument(0));
        self::assertEquals(new Reference(PushSubscriptions::class), $sender->getArgument(1));
        self::assertEquals(new Reference(VapidKeys::class), $sender->getArgument(2));
        $http = $sender->getArgument(3);
        self::assertInstanceOf(Definition::class, $http);
        self::assertSame(NoPrivateNetworkHttpClient::class, $http->getClass());
        $inner = $http->getArgument(0);
        self::assertInstanceOf(Definition::class, $inner);
        self::assertSame([HttpClient::class, 'create'], $inner->getFactory());
        self::assertSame(86400, $sender->getArgument(4));
        self::assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $sender->getArgument(5));
        self::assertEqualsWithDelta(10.0, $sender->getArgument(6), 0.0001);
        self::assertEquals(new Reference(MinishlinkWebPushSender::class), $container->getDefinition(NotificationDispatcher::class)->getArgument(8));
    }

    public function testTheOptions(): void
    {
        $container = $this->load([['store' => 'memory', 'web_push' => [
            ...self::ON,
            'public_key' => 'pub',
            'private_key' => 'priv',
            'ttl' => 600,
            'push_services' => [],
            'max_subscriptions' => 3,
            'worker' => '/sw.js',
            'http_client' => 'app.http',
            'timeout' => 5,
        ]]]);

        self::assertSame(InMemoryPushSubscriptions::class, (string) $container->getAlias(PushSubscriptions::class));
        self::assertTrue($container->getAlias(PushSubscriptions::class)->isPublic());
        self::assertSame([3], $container->getDefinition(InMemoryPushSubscriptions::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryPushSubscriptions::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(DbalPushSubscriptions::class));
        self::assertSame([], $container->getDefinition(PushSubscriber::class)->getArgument(1));
        self::assertSame('pub', $container->getDefinition(WebPushComponent::class)->getArgument(1));
        self::assertSame('/sw.js', $container->getDefinition(WebPushComponent::class)->getArgument(4));
        self::assertSame(['mailto:ops@example.com', 'pub', 'priv'], $container->getDefinition(VapidKeys::class)->getArguments());

        $sender = $container->getDefinition(MinishlinkWebPushSender::class);
        $http = $sender->getArgument(3);
        self::assertInstanceOf(Definition::class, $http);
        self::assertEquals([new Reference('app.http')], $http->getArguments());
        self::assertSame(600, $sender->getArgument(4));
        self::assertEqualsWithDelta(5.0, $sender->getArgument(6), 0.0001);
    }

    public function testClassesAreInstalledWhenTheyAllLoad(): void
    {
        self::assertTrue(NotificationsServices::installed());
        self::assertTrue(NotificationsServices::installed(WebPush::class, NoPrivateNetworkHttpClient::class));
        self::assertFalse(NotificationsServices::installed(WebPush::class, 'Phil\\Missing\\Class'));
        self::assertFalse(NotificationsServices::installed('Phil\\Missing\\Class', NoPrivateNetworkHttpClient::class));
    }

    public function testTheApplicationsOwnSenderTakesTheSubscriptions(): void
    {
        $container = $this->load([['web_push_sender' => 'app.push', 'web_push' => self::ON]]);

        self::assertFalse($container->hasDefinition(MinishlinkWebPushSender::class));
        self::assertFalse($container->hasDefinition(VapidKeys::class));
        self::assertTrue($container->hasDefinition(PushSubscriber::class));
        self::assertEquals(new Reference('app.push'), $container->getDefinition(NotificationDispatcher::class)->getArgument(8));
    }

    public function testWebPushNeedsAContact(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Web Push needs a VAPID contact: set "phil_notifications.web_push.subject" to a "mailto:" address or an "https://" page.');

        $this->load([['web_push' => ['enabled' => true]]]);
    }

    public function testTheBoundsOfTheOptions(): void
    {
        foreach ([['ttl' => -1], ['ttl' => 2_419_201], ['max_subscriptions' => 0], ['max_subscriptions' => 101], ['timeout' => 0.5], ['timeout' => 61], ['public_key' => ''], ['push_services' => ['']]] as $option) {
            try {
                $this->load([['web_push' => [...self::ON, ...$option]]]);
                self::fail((string) json_encode($option));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertNotSame('', $invalidConfigurationException->getMessage());
            }
        }

        foreach ([['max_subscriptions' => 1], ['timeout' => 1], ['timeout' => 60]] as $option) {
            $this->load([['web_push' => [...self::ON, ...$option]]]);
        }

        self::assertSame(100, $this->load([['web_push' => [...self::ON, 'max_subscriptions' => 100, 'ttl' => 0]]])->getDefinition(DbalPushSubscriptions::class)->getArgument(2));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilNotificationsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load($configs, $container);

        return $container;
    }
}
