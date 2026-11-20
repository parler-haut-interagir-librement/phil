<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit;

use Phil\CircuitBreaker\CircuitBreakers;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Delivery\RecipientRateLimit;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Infrastructure\CircuitBreaker\ChannelBreakers;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsPass;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsServices;
use Phil\NotificationsBundle\Infrastructure\Idempotency\NotificationDedupe;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerChannelSender;
use Phil\NotificationsBundle\Infrastructure\Notifier\NotifierChannelSender;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\NotificationsBundle\Tests\Unit\Fixture\ScriptedChannels;
use Phil\RateLimit\RateLimitGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilNotificationsBundle::class)]
#[CoversClass(NotificationsServices::class)]
#[CoversClass(NotificationsPass::class)]
final class DeliveryServicesTest extends TestCase
{
    public function testByDefaultNothingIsHeldBack(): void
    {
        $container = $this->load([]);
        $policy = $container->getDefinition(DeliveryPolicy::class);

        self::assertEquals(new IteratorArgument([]), $policy->getArgument(0));
        self::assertNull($policy->getArgument(1));
        self::assertNull($policy->getArgument(2));
        $breakers = $policy->getArgument(3);
        self::assertInstanceOf(Definition::class, $breakers);
        self::assertSame(ChannelBreakers::class, $breakers->getClass());
        self::assertEquals([new Reference(CircuitBreakers::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $breakers->getArguments());
        self::assertFalse($container->hasDefinition(NotificationDedupe::class));
        self::assertFalse($container->hasDefinition(RecipientRateLimit::class));
        self::assertFalse($container->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));
        self::assertSame([], RateLimitGuard::watched($container));
        self::assertEquals([new Reference('texter.transports', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference('chatter.transports', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(NotifierChannelSender::class)->getArguments());
        self::assertEquals([new Reference('mailer.transports', ContainerInterface::NULL_ON_INVALID_REFERENCE), null], $container->getDefinition(MailerChannelSender::class)->getArguments());
    }

    public function testATypeChoosesItsModeAndWindow(): void
    {
        $types = $this->load([['types' => ['alert.raised' => ['channels' => ['sms', 'email'], 'delivery' => 'first_success', 'dedupe_ttl' => 60]]]])->getDefinition(NotificationTypes::class)->getArguments();

        self::assertInstanceOf(Definition::class, $types[0]);
        self::assertSame(DeliveryMode::FirstSuccess, $types[0]->getArgument(9));
        self::assertSame(60, $types[0]->getArgument(10));
    }

    public function testTheSenderOfTheEmailsOfTheFallback(): void
    {
        self::assertSame('alerts@example.test', $this->load([['delivery' => ['from' => 'alerts@example.test']]])->getDefinition(MailerChannelSender::class)->getArgument(1));
    }

    public function testTheWindowOnAStoreOfTheApplication(): void
    {
        $container = $this->load([['delivery' => ['dedupe' => ['store' => 'app.idempotency', 'ttl' => 120]]]]);
        $dedupe = $container->getDefinition(NotificationDedupe::class);
        $guard = $dedupe->getArgument(0);

        self::assertInstanceOf(Definition::class, $guard);
        self::assertSame(IdempotencyGuard::class, $guard->getClass());
        self::assertEquals([new Reference('app.idempotency'), new Reference('clock'), 120], $guard->getArguments());
        self::assertSame(120, $dedupe->getArgument(1));
        self::assertEquals(new Reference(NotificationDedupe::class), $container->getDefinition(DeliveryPolicy::class)->getArgument(1));
        self::assertFalse($container->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));
    }

    public function testTheWindowInACachePoolIsCheckedUnlessOnOneServer(): void
    {
        $container = $this->load([['delivery' => ['dedupe' => ['cache_pool' => 'cache.redis']]]]);
        $store = $container->getDefinition(CacheIdempotencyStore::class.'.phil_notifications');
        $guard = $container->getDefinition(NotificationDedupe::class)->getArgument(0);

        self::assertSame(CacheIdempotencyStore::class, $store->getClass());
        self::assertEquals([new Reference('cache.redis'), new Reference('lock.factory'), new Reference('clock')], $store->getArguments());
        self::assertInstanceOf(Definition::class, $guard);
        self::assertEquals(new Reference(CacheIdempotencyStore::class.'.phil_notifications'), $guard->getArgument(0));
        self::assertSame(300, $guard->getArgument(2));
        self::assertSame('cache.redis', $container->getParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));

        $single = $this->load([['delivery' => ['single_server' => true, 'dedupe' => ['cache_pool' => 'cache.app', 'lock_factory' => 'app.locks']]]]);

        self::assertFalse($single->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));
        self::assertEquals(new Reference('app.locks'), $single->getDefinition(CacheIdempotencyStore::class.'.phil_notifications')->getArgument(1));
    }

    public function testTheBoundsOfTheWindows(): void
    {
        self::assertSame(1, $this->load([['delivery' => ['dedupe' => ['cache_pool' => 'cache.app', 'ttl' => 1]]]])->getDefinition(NotificationDedupe::class)->getArgument(1));

        foreach ([0, -1] as $ttl) {
            try {
                $this->load([['delivery' => ['dedupe' => ['cache_pool' => 'cache.app', 'ttl' => $ttl]]]]);
                self::fail('A window of less than a second is refused.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('Should be greater than or equal to 1', $invalidConfigurationException->getMessage());
            }
        }

        foreach ([0, 1] as $ttl) {
            $types = $this->load([['types' => ['alert.raised' => ['dedupe_ttl' => $ttl]]]])->getDefinition(NotificationTypes::class)->getArguments();

            self::assertInstanceOf(Definition::class, $types[0]);
            self::assertSame($ttl, $types[0]->getArgument(10));
        }

        $this->expectException(InvalidConfigurationException::class);

        $this->load([['types' => ['alert.raised' => ['dedupe_ttl' => -1]]]]);
    }

    public function testTheWindowNeedsAStore(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The window of duplicates needs a shared store');

        $this->load([['delivery' => ['dedupe' => true]]]);
    }

    public function testTheRateLimitIsANamedLimiterWhoseStorageIsChecked(): void
    {
        $container = $this->load([['delivery' => ['rate_limiter' => 'notification_per_user']]]);

        self::assertEquals([new Reference('limiter.notification_per_user')], $container->getDefinition(RecipientRateLimit::class)->getArguments());
        self::assertEquals(new Reference(RecipientRateLimit::class), $container->getDefinition(DeliveryPolicy::class)->getArgument(2));
        self::assertSame(['phil_notifications.delivery.rate_limiter' => ['store' => 'limiter.notification_per_user', 'switch' => null]], RateLimitGuard::watched($container));
        self::assertSame([], RateLimitGuard::watched($this->load([['delivery' => ['rate_limiter' => 'notification_per_user', 'single_server' => true]]])));
    }

    public function testTheSendersOfTheApplicationComeFirst(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->register('app.sms', ScriptedChannels::class)->addTag(NotificationsServices::CHANNEL_SENDER_TAG);
        $container->register('mailer.transports', stdClass::class);
        $container->register('texter', stdClass::class);
        $container->register('security.csrf.token_manager', stdClass::class);
        new NotificationsPass()->process($container);

        self::assertEquals(new IteratorArgument([new Reference('app.sms'), new Reference(MailerChannelSender::class), new Reference(NotifierChannelSender::class)]), $container->getDefinition(DeliveryPolicy::class)->getArgument(0));
    }

    public function testTheSendersAreReferencesAndTheApplicationsAreFoundByAutoconfiguration(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->register('app.sms', ScriptedChannels::class)->addTag(NotificationsServices::CHANNEL_SENDER_TAG);
        $container->register('security.csrf.token_manager', stdClass::class);
        new NotificationsPass()->process($container);
        $senders = $container->getDefinition(DeliveryPolicy::class)->getArgument(0);

        self::assertInstanceOf(IteratorArgument::class, $senders);
        self::assertContainsOnlyInstancesOf(Reference::class, $senders->getValues());
        self::assertArrayHasKey(NotificationsServices::CHANNEL_SENDER_TAG, $container->getAutoconfiguredInstanceof()[ChannelSender::class]->getTags());
    }

    public function testASenderWithNothingToSendThroughIsLeftOut(): void
    {
        foreach (['chatter' => [new Reference(NotifierChannelSender::class)], 'none' => []] as $service => $senders) {
            $container = $this->load([['store' => 'memory']]);
            $container->register('security.csrf.token_manager', stdClass::class);

            if ('none' !== $service) {
                $container->register($service, stdClass::class);
            }

            new NotificationsPass()->process($container);

            self::assertEquals(new IteratorArgument($senders), $container->getDefinition(DeliveryPolicy::class)->getArgument(0), $service);
        }
    }

    public function testTheSenderGetsTheTransportsOfATexterOrAChatterOnlyWhenOneIsConfigured(): void
    {
        $transports = [
            'texter.transports' => new Reference('texter.transports', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            'chatter.transports' => new Reference('chatter.transports', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ];

        foreach (['texter' => [$transports['texter.transports'], null], 'chatter' => [null, $transports['chatter.transports']], 'both' => array_values($transports), 'none' => [null, null]] as $configured => $arguments) {
            $container = $this->load([['store' => 'memory']]);
            $container->register('security.csrf.token_manager', stdClass::class);
            $container->register('texter.transports', stdClass::class);
            $container->register('chatter.transports', stdClass::class);

            foreach (['texter', 'chatter'] as $service) {
                if (\in_array($configured, [$service, 'both'], true)) {
                    $container->register($service, stdClass::class);
                }
            }

            new NotificationsPass()->process($container);

            self::assertEquals($arguments, $container->getDefinition(NotifierChannelSender::class)->getArguments(), $configured);
        }
    }

    public function testAWindowLocalToEachServerIsRefusedInProduction(): void
    {
        $container = $this->production('cache.local', FilesystemAdapter::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_notifications.delivery.dedupe.cache_pool: the pool "cache.local" is local to each server, so every server would send the same notification once.');

        new NotificationsPass()->process($container);
    }

    public function testASharedWindowOrAnotherEnvironmentPasses(): void
    {
        $shared = $this->production('cache.redis', RedisAdapter::class);
        new NotificationsPass()->process($shared);

        self::assertFalse($shared->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));

        $test = $this->production('cache.local', FilesystemAdapter::class);
        $test->setParameter('kernel.environment', 'test');
        new NotificationsPass()->process($test);

        self::assertFalse($test->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));
    }

    public function testAParameterThatIsNoServiceIdIsDropped(): void
    {
        $container = $this->production('cache.local', FilesystemAdapter::class);
        $container->setParameter(NotificationsServices::DEDUPE_POOL_PARAMETER, ['cache.local']);
        new NotificationsPass()->process($container);

        self::assertFalse($container->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER));
    }

    public function testTheBundleChecksTheStoresOfTheRateLimit(): void
    {
        $container = new ContainerBuilder();
        new PhilNotificationsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
    }

    private function production(string $pool, string $class): ContainerBuilder
    {
        $container = $this->load([['store' => 'memory', 'delivery' => ['dedupe' => ['cache_pool' => $pool]]]]);
        $container->register('security.csrf.token_manager', stdClass::class);
        $container->register($pool, $class);
        $container->setParameter('kernel.environment', 'prod');

        return $container;
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
