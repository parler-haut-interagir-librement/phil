<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit;

use Phil\EmailAddress\EmailValidator;
use Phil\Markup\Markup;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;
use Phil\NewsletterBundle\Application\Contract\SendThrottle;
use Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchemaListener;
use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Phil\NewsletterBundle\Infrastructure\Webhook\AudienceWebhookListener;
use Phil\NewsletterBundle\PhilNewsletterBundle;
use Phil\NewsletterBundle\Tests\Unit\Fixture\DefinitionExporter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * The services the bundle registers, each with what it is given, written as text.
 */
#[CoversClass(NewsletterServices::class)]
#[CoversClass(PhilNewsletterBundle::class)]
final class NewsletterServicesTest extends TestCase
{
    private const array FULL = [
        'secret' => 'a-secret-of-sixteen-bytes',
        'connection' => 'doctrine.dbal.news_connection',
        'check_mail_domain' => false,
        'site' => 'main',
        'sites' => ['main' => ['base_url' => 'https://example.com'], 'shop' => ['base_url' => 'https://shop.example.com']],
        'default_profile' => '2024',
        'profiles' => [
            '2024' => ['from' => 'news@example.com', 'from_name' => 'News', 'reply_to' => 'reply@example.com', 'transport' => 'bulk', 'unsubscribe_mailbox' => 'stop@example.com', 'window' => ['days' => [1, 2, 3], 'from' => '09:00', 'until' => '17:00', 'timezone' => 'America/Toronto'], 'daily_cap' => 1000, 'rate' => 30, 'domain_rates' => ['gmail.com' => 10]],
            'plain' => ['from' => 'plain@example.com'],
        ],
        'clicks' => ['minimum_seconds' => 4],
        'bounces' => [
            'soft_threshold' => 5,
            'verp' => ['enabled' => true, 'local_part' => 'rebonds', 'domain' => 'bounce.example.com', 'separator' => '-'],
            'imap' => ['enabled' => true, 'host' => 'imap.example.com', 'port' => 143, 'user' => 'bounces', 'password' => 'secret', 'folder' => 'Bounces', 'delete' => true],
            'remote_event_consumer' => 'provider',
        ],
        'rate_limiter_storage' => 'cache.news_limiter',
        'webhooks' => ['crm' => ['url' => 'https://crm.example.com/hooks', 'events' => ['subscribed', 'unsubscribed']], 'all' => ['url' => 'https://all.example.com/hooks']],
        'preview_role' => 'ROLE_EDITOR',
    ];

    public function testTheDefaultServices(): void
    {
        $container = $this->load([]);

        Snapshot::assertSame('services-default', DefinitionExporter::export($container));
    }

    public function testTheServicesOfAFullConfiguration(): void
    {
        $container = $this->load(self::FULL, ['doctrine']);

        Snapshot::assertSame('services-full', DefinitionExporter::export($container));
    }

    public function testTheSchemaListenerNeedsTheDoctrineExtension(): void
    {
        self::assertFalse($this->load([])->hasDefinition(NewsletterSchemaListener::class));
        self::assertTrue($this->load([], ['doctrine'])->hasDefinition(NewsletterSchemaListener::class));
    }

    public function testTheValidatorAndTheMarkupOfTheApplicationAreKept(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(Markup::class, new Definition('stdClass'));
        $builder->setDefinition(EmailValidator::class, new Definition('stdClass'));

        $container = $this->load([], [], $builder);

        self::assertSame('stdClass', $container->getDefinition(Markup::class)->getClass());
        self::assertSame('stdClass', $container->getDefinition(EmailValidator::class)->getClass());
        self::assertSame([], $container->getDefinition(EmailValidator::class)->getArguments());
    }

    public function testAConfigurationOfTheWrongTypesIsReadAsEmptyValues(): void
    {
        $config = [
            ...$this->processed(self::FULL),
            'clicks' => ['minimum_seconds' => 'x'],
            'bounces' => [
                'soft_threshold' => 'x',
                'verp' => ['enabled' => true, 'local_part' => 'rebonds', 'domain' => 'bounce.example.com', 'separator' => '-'],
                'imap' => ['enabled' => true, 'host' => 5, 'port' => 'x', 'user' => 'bounces', 'password' => 'secret', 'folder' => 'Bounces', 'delete' => true],
                'remote_event_consumer' => 'provider',
            ],
            'webhooks' => ['crm' => ['url' => 'https://crm.example.com/hooks', 'events' => ['first' => 'subscribed', 'second' => 4]], 'odd' => ['url' => 5, 'events' => 'x']],
        ];

        $container = $this->load($config, [], null, false);

        self::assertSame(0, $container->getDefinition(ClickTracker::class)->getArgument(6));
        self::assertSame(0, $container->getDefinition(BounceHandler::class)->getArgument(9));
        $imap = $container->getDefinition(BounceMailbox::class)->getArguments();
        self::assertSame(['', 0], [$imap[0], $imap[1]]);
        $listener = $container->getDefinition(AudienceWebhookListener::class)->getArgument(1);
        self::assertSame(['crm' => ['subscribed', ''], 'odd' => []], $listener);
        self::assertSame(['crm' => 'https://crm.example.com/hooks', 'odd' => ''], $container->getDefinition(DeliverAudienceWebhookHandler::class)->getArgument(2));
        self::assertTrue($container->hasDefinition(SendThrottle::class));
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string>            $extensions
     */
    private function load(array $config, array $extensions = [], ?ContainerBuilder $builder = null, bool $processed = true): ContainerBuilder
    {
        $builder ??= new ContainerBuilder();
        $builder->setParameter('kernel.secret', 'a-secret-of-sixteen-bytes');

        foreach ($extensions as $alias) {
            $builder->registerExtension(new class($alias) extends Extension {
                public function __construct(
                    private readonly string $alias,
                ) {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        $bundle = new PhilNewsletterBundle();

        if ($processed) {
            $extension = $bundle->getContainerExtension();
            self::assertNotNull($extension);
            $extension->load([$config], $builder);

            return $builder;
        }

        $instanceof = [];
        $bundle->loadExtension($config, new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator()), $instanceof, __FILE__, __FILE__), $builder);

        return $builder;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilNewsletterBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
