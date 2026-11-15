<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit;

use Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;
use Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler;
use Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler;
use Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Infrastructure\Automations\SendAction;
use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Phil\NewsletterBundle\Infrastructure\Privacy\NewsletterPrivacy;
use Phil\NewsletterBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\NewsletterBundle\PhilNewsletterBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(PhilNewsletterBundle::class)]
#[CoversClass(NewsletterServices::class)]
final class PhilNewsletterBundleTest extends TestCase
{
    public function testTheDefaultsRegisterTheServicesAndTheIntegrationsInstalled(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getParameter('phil_newsletter.enabled'));
        self::assertTrue($container->hasDefinition(SendDeliveryHandler::class));
        self::assertTrue($container->getDefinition(SendDeliveryHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->hasDefinition('phil_newsletter.profile.default'));
        self::assertSame('newsletter@example.com', $container->getDefinition('phil_newsletter.profile.default')->getArgument(1));
        self::assertTrue($container->hasDefinition(SenderProfiles::class));
        self::assertTrue($container->getDefinition(SendAction::class)->hasTag('phil.automations.action'));
        self::assertTrue($container->hasDefinition('phil_newsletter.trigger.confirmed'));
        self::assertTrue($container->hasDefinition(NewsletterPrivacy::class));
        self::assertFalse($container->hasDefinition(BounceMailbox::class));
        self::assertFalse($container->hasDefinition(FetchBouncesHandler::class));
        self::assertFalse($container->hasDefinition(MailerEventConsumer::class));
        self::assertFalse($container->hasDefinition(AudienceWebhookSender::class));
        self::assertNull($container->getDefinition(MessageComposer::class)->getArgument(0));
    }

    public function testTheOptionalPartsFollowTheConfiguration(): void
    {
        $container = $this->load([[
            'profiles' => ['news' => ['from' => 'news@example.com', 'window' => ['days' => [1, 2], 'from' => '09:00', 'until' => '17:00', 'timezone' => 'America/Toronto'], 'daily_cap' => 1000, 'domain_rates' => ['gmail.com' => 30]]],
            'default_profile' => 'news',
            'bounces' => ['verp' => ['domain' => 'example.com'], 'imap' => ['host' => 'imap.example.com', 'user' => 'u', 'password' => 'p'], 'remote_event_consumer' => 'provider'],
            'webhooks' => ['crm' => ['url' => 'https://crm.example.com/hooks', 'events' => ['unsubscribed']]],
            'sites' => ['default' => ['base_url' => 'https://example.com']],
        ]]);

        self::assertTrue($container->hasDefinition('phil_newsletter.profile.news'));
        self::assertFalse($container->hasDefinition('phil_newsletter.profile.default'));
        self::assertSame(1000, $container->getDefinition('phil_newsletter.profile.news')->getArgument(6));
        self::assertTrue($container->hasDefinition(BounceMailbox::class));
        self::assertTrue($container->getDefinition(FetchBouncesHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame([['consumer' => 'provider']], $container->getDefinition(MailerEventConsumer::class)->getTag('remote_event.consumer'));
        self::assertTrue($container->hasDefinition(AudienceWebhookSender::class));
        self::assertSame(['crm' => 'https://crm.example.com/hooks'], $container->getDefinition(DeliverAudienceWebhookHandler::class)->getArgument(2));
        self::assertNotNull($container->getDefinition(MessageComposer::class)->getArgument(0));
        self::assertSame(['default' => 'https://example.com'], $container->getDefinition(NewsletterUrls::class)->getArgument(1));
    }

    public function testTheBundleCanBeDisabled(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->getParameter('phil_newsletter.enabled'));
        self::assertFalse($container->hasDefinition(SendDeliveryHandler::class));
    }

    public function testTheConfigurationIsChecked(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['profiles' => ['news' => ['from' => 'a@example.com', 'rate' => 0]]]]);
    }

    public function testTheAlias(): void
    {
        self::assertSame('phil_newsletter', $this->extension()->getAlias());
    }

    public function testTheAssetsArePrependedForAssetMapper(): void
    {
        self::assertDirectoryExists(PhilNewsletterBundle::ASSETS_DIR.'/src');
        self::assertFileExists(PhilNewsletterBundle::ASSETS_DIR.'/src/markdown_editor_controller.js');
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 'a-secret-of-sixteen-bytes');
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilNewsletterBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
