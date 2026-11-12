<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\DependencyInjection;

use DateTimeImmutable;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitHeaders;
use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Infrastructure\Form\RateLimitTypeExtension;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitCacheWarmer;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitListener;
use Phil\RateLimitBundle\Infrastructure\Http\TooManyRequestsResponder;
use Phil\RateLimitBundle\Infrastructure\IpAnonymizer\NetworkAddress;
use Phil\RateLimitBundle\Infrastructure\Key\ClientIpKey;
use Phil\RateLimitBundle\Infrastructure\Key\IpRouteKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserOrIpKey;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Throwable;

/**
 * The services of the configuration: the key strategies, the limiters over `symfony/rate-limiter` with their storage,
 * the enforcer, the listener of the controllers and routes, the responder of the refusals, and the form extension.
 *
 * Every limiter's options are checked here, by building it once on a memory storage, so that a bad one fails the
 * container build; `LimitersPass` then checks that the storage, the lock factories and the key strategies exist.
 *
 * @phpstan-type LimiterConfig array{policy: string|null, service: string|null, limit: int|null, interval: string|null, rate: array{interval: string|null, amount: int}, anchor_at: string|null, limiters: list<string>, key: string, amount: int, cache_pool: string|null, storage: string|null, lock_factory: string|null, observe: bool}
 * @phpstan-type Config array{
 *     secret: string,
 *     headers: string,
 *     error_template: string,
 *     cache_pool: string,
 *     lock_factory: string|null,
 *     single_server: bool,
 *     network: array{enabled: bool, ipv4_prefix: int, ipv6_prefix: int},
 *     limiters: array<array-key, LimiterConfig>,
 *     forms: array<array-key, mixed>,
 * }
 */
final class RateLimitServices
{
    /** What a limiter's name is: it names the policy of the headers, a structured-field string. */
    public const string NAME_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/D';

    /** The tag of a limiter, with its `name` and, optionally, its default `key` and `amount` and `observe`. */
    public const string LIMITER_TAG = 'phil_rate_limit.limiter';

    /** The tag of a key strategy, with its `strategy` name; a service implementing `KeyResolver` is tagged by its id. */
    public const string KEY_TAG = 'phil_rate_limit.key_resolver';

    /** The hidden parameter of the services the limiters need, checked then removed by `LimitersPass`. */
    public const string REQUIRED_PARAMETER = '.phil_rate_limit.required_services';

    /** The hidden parameter of the form rules, checked by `LimitersPass`. */
    public const string FORMS_PARAMETER = '.phil_rate_limit.forms';

    public const string IP_KEY = 'phil_rate_limit.key.ip';

    public const string USER_KEY = 'phil_rate_limit.key.user';

    public const string USER_OR_IP_KEY = 'phil_rate_limit.key.user_or_ip';

    public const string IP_ROUTE_KEY = 'phil_rate_limit.key.ip_route';

    public const string NETWORK_ADDRESS = 'phil_rate_limit.network_address';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::keys($container, $config);

        $required = [];

        foreach ($config['limiters'] as $name => $limiter) {
            $required += self::limiter($container, (string) $name, $limiter, $config);
        }

        $forms = self::forms($config['forms']);
        $container->setParameter(self::REQUIRED_PARAMETER, $required);
        $container->setParameter(self::FORMS_PARAMETER, $forms);

        $container->register(RateLimitHeaders::class, RateLimitHeaders::class)
            ->setArguments([HeaderStyle::from($config['headers'])]);
        $container->register(Limiters::class, Limiters::class)
            ->setArguments([null, []]);
        $container->register(KeyResolvers::class, KeyResolvers::class)
            ->setArguments([null, []]);
        $container->register(RateLimitEnforcer::class, RateLimitEnforcer::class)
            ->setArguments([new Reference(Limiters::class), new Reference(KeyResolvers::class), new Reference(RateLimitHeaders::class), new Reference('event_dispatcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->addTag('monolog.logger', ['channel' => 'rate_limit'])
            ->setPublic(true);

        $container->register(RateLimitListener::class, RateLimitListener::class)
            ->setArguments([new Reference(RateLimitEnforcer::class)])
            ->addTag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController'])
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
        $container->register(RateLimitCacheWarmer::class, RateLimitCacheWarmer::class)
            ->setArguments([new Reference(Limiters::class), new Reference(KeyResolvers::class), new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), []])
            ->addTag('kernel.cache_warmer');
        $container->register(TooManyRequestsResponder::class, TooManyRequestsResponder::class)
            ->setArguments([new Reference('twig', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $config['error_template'], new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->addTag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onKernelException']);

        if (class_exists(AbstractTypeExtension::class)) {
            $container->register(RateLimitTypeExtension::class, RateLimitTypeExtension::class)
                ->setArguments([new Reference(RateLimitEnforcer::class), new Reference('request_stack'), $forms, new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
                ->addTag('form.type_extension');
        }
    }

    /**
     * The four key strategies of the bundle, and the network of the addresses with `phil/ip-anonymizer`.
     *
     * @param Config $config
     */
    private static function keys(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(KeyResolver::class)->addTag(self::KEY_TAG);

        $network = null;

        if ($config['network']['enabled']) {
            if (!class_exists(Truncation::class)) {
                throw new InvalidConfigurationException('phil_rate_limit.network needs phil/ip-anonymizer: install it, or turn the network off.');
            }

            $container->register(self::NETWORK_ADDRESS, NetworkAddress::class)
                ->setArguments([new Definition(Truncation::class, [$config['network']['ipv4_prefix'], $config['network']['ipv6_prefix']])]);
            $network = new Reference(self::NETWORK_ADDRESS);
        }

        $container->register(self::IP_KEY, ClientIpKey::class)
            ->setArguments([$config['secret'], $network])
            ->addTag(self::KEY_TAG, ['strategy' => 'ip']);
        $container->register(self::USER_KEY, UserKey::class)
            ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['secret']])
            ->addTag(self::KEY_TAG, ['strategy' => 'user']);
        $container->register(self::USER_OR_IP_KEY, UserOrIpKey::class)
            ->setArguments([new Reference(self::USER_KEY), new Reference(self::IP_KEY)])
            ->addTag(self::KEY_TAG, ['strategy' => 'user_or_ip']);
        $container->register(self::IP_ROUTE_KEY, IpRouteKey::class)
            ->setArguments([new Reference(self::IP_KEY), $config['secret']])
            ->addTag(self::KEY_TAG, ['strategy' => 'ip_route']);
    }

    /**
     * One limiter of the configuration: its factory, its storage and its `SymfonyLimiter`, tagged with its defaults.
     *
     * @param LimiterConfig $limiter
     * @param Config        $config
     *
     * @return array<string, string> the services it needs, by id, with what needs them
     */
    private static function limiter(ContainerBuilder $container, string $name, array $limiter, array $config): array
    {
        $path = 'phil_rate_limit.limiters.'.$name;

        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidConfigurationException(\sprintf('%s: a limiter\'s name is 1 to 64 letters, digits, "_", "." and "-".', $path));
        }

        if ((null === $limiter['policy']) === (null === $limiter['service'])) {
            throw new InvalidConfigurationException(\sprintf('%s: a limiter has either a policy or a service.', $path));
        }

        if (null !== $limiter['limit'] && $limiter['amount'] > $limiter['limit']) {
            throw new InvalidConfigurationException(\sprintf('%s: one request cannot consume %d of a limit of %d.', $path, $limiter['amount'], $limiter['limit']));
        }

        $factory = 'phil_rate_limit.factory.'.$name;
        $required = [];
        $window = null;

        if (null !== $limiter['service']) {
            $factory = $limiter['service'];
            $required[$factory] = $path.'.service';
        } elseif ('compound' === $limiter['policy']) {
            if ([] === $limiter['limiters']) {
                throw new InvalidConfigurationException(\sprintf('%s: a compound limiter names the limiters it counts.', $path));
            }

            foreach ($limiter['limiters'] as $part) {
                $partConfig = $config['limiters'][$part] ?? null;

                if (null === $partConfig || 'compound' === $partConfig['policy']) {
                    throw new InvalidConfigurationException(\sprintf('%s: "%s" is not a limiter of a policy or a service declared beside it.', $path, $part));
                }
            }

            $container->register($factory, CompoundRateLimiterFactory::class)
                ->setArguments([array_map(static fn (string $part): Reference => new Reference(self::factoryOf($part, $config)), $limiter['limiters'])]);
        } else {
            $options = self::options($path, $name, $limiter);
            $storage = $limiter['storage'] ?? 'phil_rate_limit.storage.'.$name;
            $lock = $limiter['lock_factory'] ?? $config['lock_factory'];

            if (null === $limiter['storage']) {
                $pool = $limiter['cache_pool'] ?? $config['cache_pool'];
                $container->register($storage, CacheStorage::class)->setArguments([new Reference($pool)]);
                $required[$pool] = $path.' (its cache pool)';
                self::watch($container, $pool, $path.'.cache_pool', $limiter, $config);
            } else {
                $required[$storage] = $path.'.storage';
                self::watch($container, $storage, $path.'.storage', $limiter, $config);
            }

            if (null !== $lock) {
                $required[$lock] = $path.' (its lock factory)';
            }

            $container->register($factory, RateLimiterFactory::class)
                ->setArguments([$options, new Reference($storage), null === $lock ? null : new Reference($lock)]);
            $window = \in_array($limiter['policy'], ['fixed_window', 'sliding_window'], true) ? self::seconds((string) $limiter['interval']) : null;
        }

        $container->register('phil_rate_limit.limiter.'.$name, SymfonyLimiter::class)
            ->setArguments([new Reference($factory), $window])
            ->addTag(self::LIMITER_TAG, ['name' => $name, 'key' => $limiter['key'], 'amount' => $limiter['amount'], 'observe' => $limiter['observe']]);

        return $required;
    }

    /**
     * A limiter that counts is held to a store shared by the servers, unless the application says it runs on one.
     *
     * @param LimiterConfig $limiter
     * @param Config        $config
     */
    private static function watch(ContainerBuilder $container, string $store, string $path, array $limiter, array $config): void
    {
        if ('no_limit' !== $limiter['policy']) {
            RateLimitGuard::watch($container, $store, $path, $config['single_server']);
        }
    }

    /**
     * The options of a `RateLimiterFactory`, checked by building one on a memory storage.
     *
     * @param LimiterConfig $limiter
     *
     * @return array<string, mixed>
     */
    private static function options(string $path, string $name, array $limiter): array
    {
        $options = array_filter([
            'id' => 'phil_rate_limit.'.$name,
            'policy' => $limiter['policy'],
            'limit' => $limiter['limit'],
            'interval' => $limiter['interval'],
            'rate' => null === $limiter['rate']['interval'] ? null : $limiter['rate'],
            'anchor_at' => $limiter['anchor_at'],
        ], static fn (mixed $option): bool => null !== $option);

        if ('no_limit' !== $limiter['policy'] && null === $limiter['limit']) {
            throw new InvalidConfigurationException(\sprintf('%s: the policy "%s" needs a limit.', $path, (string) $limiter['policy']));
        }

        try {
            new RateLimiterFactory($options, new InMemoryStorage())->create('check');
        } catch (Throwable $throwable) {
            throw new InvalidConfigurationException(\sprintf('%s: %s', $path, $throwable->getMessage()), 0, $throwable);
        }

        return $options;
    }

    /**
     * @param Config $config
     */
    private static function factoryOf(string $name, array $config): string
    {
        return $config['limiters'][$name]['service'] ?? 'phil_rate_limit.factory.'.$name;
    }

    /**
     * The seconds of an interval the factory accepted, counted from the epoch.
     */
    private static function seconds(string $interval): int
    {
        return new DateTimeImmutable('@0')->modify($interval)->getTimestamp();
    }

    /**
     * The rules of the `forms` section, each checked, as plain arrays the container can dump.
     *
     * @param array<array-key, mixed> $forms
     *
     * @return array<string, list<array{limiter: string, amount: int|null, key: string|null}>>
     */
    private static function forms(array $forms): array
    {
        $rules = [];

        foreach ($forms as $form => $value) {
            try {
                foreach (RateLimitRules::from($value) as $rule) {
                    $rules[(string) $form][] = ['limiter' => $rule->limiter, 'amount' => $rule->amount, 'key' => $rule->key];
                }
            } catch (InvalidRateLimit $invalidRateLimit) {
                throw new InvalidConfigurationException(\sprintf('phil_rate_limit.forms.%s: %s', $form, $invalidRateLimit->getMessage()), 0, $invalidRateLimit);
            }
        }

        return $rules;
    }
}
