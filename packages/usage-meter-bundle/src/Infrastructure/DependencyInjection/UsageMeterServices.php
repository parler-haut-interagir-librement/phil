<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Infrastructure\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use InvalidArgumentException;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Dbal\UsageSchemaListener;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\LimitChain;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Subscription\FeatureLimits;
use Phil\UsageMeter\UsageMeter;
use Phil\UsageMeter\UsageStore;
use Phil\UsageMeterBundle\Infrastructure\Configuration\ConfiguredMeters;
use Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand;
use Phil\UsageMeterBundle\Infrastructure\RateLimit\MeterLimiter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the configuration: the meters, the store — in the database, in memory, or the application's —, the
 * chain of limit providers, the anchor provider, `UsageMeter`, the purge command, and the rate limiters that
 * `phil/rate-limit-bundle` holds requests to.
 *
 * @phpstan-type MeterConfig array{limit: mixed, calendar: string|null, timezone: string, rolling: int|null, buckets: int, billing: string|null, months: int}
 * @phpstan-type Config array{
 *     store: string,
 *     dbal: array{connection: string, counter_table: string, key_table: string},
 *     clock: string,
 *     limit_providers: list<string>,
 *     subscription_features: array{enabled: bool, prefix: string},
 *     anchor_provider: string|null,
 *     meters: array<array-key, MeterConfig>,
 *     rate_limiters: array<array-key, array{meter: string, key: string, amount: int, idempotency_key: bool}>,
 *     idempotency_secret: string,
 * }
 */
final class UsageMeterServices
{
    /** The `store` that keeps the counters in two tables of a DBAL connection. */
    public const string STORE_DBAL = 'dbal';

    /** The `store` that keeps the counters in memory, for tests and single runs. */
    public const string STORE_MEMORY = 'memory';

    /** The chain of the configured limit providers. */
    public const string LIMITS = 'phil_usage_meter.limits';

    /** The limits read from the features of `phil/commerce-subscription-bundle`. */
    public const string SUBSCRIPTION_LIMITS = 'phil_usage_meter.limits.subscription';

    /** The prefix of the service id of each rate limiter, followed by its name. */
    public const string RATE_LIMITER_PREFIX = 'phil_usage_meter.rate_limiter.';

    /** The tag `phil/rate-limit-bundle` collects its limiters by. */
    public const string RATE_LIMITER_TAG = 'phil_rate_limit.limiter';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::validate($config);

        $container->register(Meters::class, Meters::class)
            ->setFactory([ConfiguredMeters::class, 'fromConfiguration'])
            ->setArguments([$config['meters']]);

        $container->setAlias(UsageStore::class, self::store($container, $config));

        $limits = $config['limit_providers'];

        if ($config['subscription_features']['enabled']) {
            $container->register(self::SUBSCRIPTION_LIMITS, FeatureLimits::class)
                ->setFactory([FeatureLimits::class, 'fromSubscriptions'])
                ->setArguments([new Reference(FeatureAccess::class), $config['subscription_features']['prefix']]);
            $limits[] = self::SUBSCRIPTION_LIMITS;
        }

        if ([] !== $limits) {
            $container->register(self::LIMITS, LimitChain::class)
                ->setArguments(array_map(static fn (string $id): Reference => new Reference($id), $limits));
        }

        $container->register(UsageMeter::class, UsageMeter::class)
            ->setArguments([
                new Reference(Meters::class),
                new Reference(UsageStore::class),
                new Reference($config['clock']),
                [] === $limits ? null : new Reference(self::LIMITS),
                null === $config['anchor_provider'] ? null : new Reference($config['anchor_provider']),
            ])
            ->setPublic(true);

        $container->register(PurgeUsageCommand::class, PurgeUsageCommand::class)
            ->setArguments([new Reference(UsageMeter::class)])
            ->addTag('console.command');

        self::rateLimiters($container, $config);
    }

    /**
     * The store's service id: the DBAL store and its schema listener, the in-memory store, or the application's.
     *
     * @param Config $config
     */
    private static function store(ContainerBuilder $container, array $config): string
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryUsageStore::class, InMemoryUsageStore::class);

            return InMemoryUsageStore::class;
        }

        if (self::STORE_DBAL !== $config['store']) {
            return $config['store'];
        }

        if (!class_exists(Connection::class)) {
            throw new InvalidConfigurationException('phil_usage_meter.store "dbal" needs doctrine/dbal: install it, or choose "memory" or a service.');
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['dbal']['connection']));
        $container->register(DbalUsageStore::class, DbalUsageStore::class)
            ->setArguments([$connection, $config['dbal']['counter_table'], $config['dbal']['key_table']]);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(UsageSchemaListener::class, UsageSchemaListener::class)
                ->setArguments([$connection, new Reference(DbalUsageStore::class)])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['dbal']['connection']]);
        }

        return DbalUsageStore::class;
    }

    /**
     * One limiter of `phil/rate-limit-bundle` per entry of `rate_limiters`, each counting on its meter, tagged with its
     * name, its key strategy and its amount.
     *
     * @param Config $config
     */
    private static function rateLimiters(ContainerBuilder $container, array $config): void
    {
        if ([] !== $config['rate_limiters'] && $container->hasParameter('kernel.bundles') && !\array_key_exists('PhilRateLimitBundle', (array) $container->getParameter('kernel.bundles'))) {
            throw new InvalidConfigurationException('phil_usage_meter.rate_limiters are limiters of phil/rate-limit-bundle: register PhilRateLimitBundle in config/bundles.php.');
        }

        foreach ($config['rate_limiters'] as $name => $limiter) {
            $container->register(self::RATE_LIMITER_PREFIX.$name, MeterLimiter::class)
                ->setArguments([new Reference(UsageMeter::class), new Reference($config['clock']), (string) $name, $limiter['meter'], $limiter['idempotency_key'], $config['idempotency_secret']])
                ->addTag(self::RATE_LIMITER_TAG, ['name' => (string) $name, 'key' => $limiter['key'], 'amount' => $limiter['amount']]);
        }
    }

    /**
     * Builds the meters once, so that a bad one fails the container build, and checks that every limiter counts on a
     * declared meter.
     *
     * @param Config $config
     */
    private static function validate(array $config): void
    {
        try {
            $meters = ConfiguredMeters::fromConfiguration($config['meters']);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidConfigurationException('phil_usage_meter.meters: '.$invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        foreach ($config['rate_limiters'] as $name => $limiter) {
            if (!$meters->has($limiter['meter'])) {
                throw new InvalidConfigurationException(\sprintf('phil_usage_meter.rate_limiters.%s counts on the meter "%s", which the meters section does not declare.', $name, $limiter['meter']));
            }
        }
    }
}
