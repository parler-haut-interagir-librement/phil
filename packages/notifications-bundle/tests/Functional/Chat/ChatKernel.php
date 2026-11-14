<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\Chat;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application that posts its announcements to a Slack channel and a Discord channel, through the chatter transports
 * of Symfony Notifier and the bridges `symfony/slack-notifier` and `symfony/discord-notifier`. The HTTP client is a
 * recorder that answers as Slack and Discord answer.
 */
final class ChatKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-notifications-bundle-test/chat/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-notifications-bundle-test/chat/log';
    }

    /**
     * Beside the kernel, out of a `config/` directory, where the kernel would write its configuration reference.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'http_client' => ['mock_response_factory' => ChatServers::class],
            'notifier' => ['chatter_transports' => [
                'slack' => 'slack://xoxb-token@default?channel=C0RELEASES',
                'discord' => 'discord://webhook-token@default?webhook_id=1234',
            ]],
        ]);
        $container->extension('phil_notifications', [
            'store' => 'memory',
            'message_bus' => null,
            'types' => [
                'release.published' => [
                    'channels' => [],
                    'broadcast' => ['chat/slack', 'chat/discord'],
                    'title' => 'A new release is out',
                    'body' => 'The notes are on the site.',
                ],
                'release.drafted' => ['channels' => [], 'title' => 'A release is drafted', 'body' => 'Review it.'],
            ],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set(ChatServers::class)->public();
        // FrameworkBundle registers a bridge's factory only when FrameworkBundle itself is a production dependency, which
        // it is for an application and not for the bundle installed on its own: the factories are registered here, under
        // FrameworkBundle's own ids.
        foreach (['slack' => SlackTransportFactory::class, 'discord' => DiscordTransportFactory::class] as $scheme => $factory) {
            $services->set('notifier.transport_factory.'.$scheme, $factory)
                ->args([new Reference('event_dispatcher'), new Reference('http_client')])
                ->tag('chatter.transport_factory');
        }
    }
}
