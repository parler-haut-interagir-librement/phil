<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle;

use Override;
use Phil\HttpCacheTagsBundle\Application\CacheTagPurger;
use Phil\HttpCacheTagsBundle\Application\CacheTagResolver;
use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\HttpCacheTagsBundle\Infrastructure\Doctrine\InvalidateOnFlushListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\InvalidationFlushListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\ResponseTagsListener;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\CloudflarePurger;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\VarnishPurger;
use Phil\HttpCacheTagsBundle\Infrastructure\Twig\CacheTagExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Twig\Attribute\AsTwigFunction;

/**
 * HTTP cache tags: gathered while a response is rendered — a controller's `#[CacheTag]`, a template's `cache_tag()`,
 * `ResponseTags` —, written into the header the cache in front reads; purged at the end of a request, a command or a
 * worker's message when an entity changes or the application says so, from Varnish or Cloudflare.
 */
final class PhilHttpCacheTagsBundle extends AbstractBundle
{
    public const string RESOLVER_TAG = 'phil_http_cache_tags.resolver';

    /** Messenger's events, only named in listener tags: the bundle does not require Messenger. */
    public const string WORKER_MESSAGE_HANDLED = WorkerMessageHandledEvent::class;

    public const string WORKER_MESSAGE_FAILED = WorkerMessageFailedEvent::class;

    private const array HEADERS = ['xkey' => ['xkey', ' '], 'cache-tag' => ['Cache-Tag', ','], 'surrogate-key' => ['Surrogate-Key', ' ']];

    #[Override]
    protected string $extensionAlias = 'phil_http_cache_tags';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('header')
                    ->info('The header the cache in front reads: xkey for Varnish, cache-tag for Cloudflare, surrogate-key for Fastly.')
                    ->values(array_keys(self::HEADERS))
                    ->defaultValue('xkey')
                ->end()
                ->enumNode('purger')
                    ->info('Which cache to purge: none, varnish or cloudflare; a CacheTagPurger service of the application replaces it.')
                    ->values(['none', 'varnish', 'cloudflare'])
                    ->defaultValue('none')
                ->end()
                ->arrayNode('varnish')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('urls')->scalarPrototype()->end()->defaultValue([])->end()
                        ->scalarNode('header')->defaultValue('xkey-purge')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('cloudflare')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('zone')->defaultNull()->end()
                        ->scalarNode('token')->defaultNull()->end()
                    ->end()
                ->end()
                ->booleanNode('invalidate_entities')
                    ->info('Whether the tags of the entities a Doctrine flush changes are invalidated.')
                    ->defaultTrue()
                ->end()
            ->end()
            ->validate()
                ->ifTrue($this->lacksVarnishUrls(...))
                ->thenInvalid('Purging Varnish needs its URLs: phil_http_cache_tags.varnish.urls.')
            ->end()
            ->validate()
                ->ifTrue($this->lacksCloudflareSettings(...))
                ->thenInvalid('Purging Cloudflare needs its zone and an API token: phil_http_cache_tags.cloudflare.')
            ->end();
    }

    /**
     * @param array{purger: string, varnish: array{urls: list<string>}} $config
     */
    private function lacksVarnishUrls(array $config): bool
    {
        return 'varnish' === $config['purger'] && [] === $config['varnish']['urls'];
    }

    /**
     * @param array{purger: string, cloudflare: array{zone: ?string, token: ?string}} $config
     */
    private function lacksCloudflareSettings(array $config): bool
    {
        return 'cloudflare' === $config['purger'] && (null === $config['cloudflare']['zone'] || null === $config['cloudflare']['token']);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{header: string, purger: string, varnish: array{urls: list<string>, header: string}, cloudflare: array{zone: ?string, token: ?string}, invalidate_entities: bool} $config */
        $builder->registerForAutoconfiguration(CacheTagResolver::class)->addTag(self::RESOLVER_TAG);
        $services = $container->services();
        [$header, $separator] = self::HEADERS[$config['header']];

        $services->set(ResponseTags::class)->tag('kernel.reset', ['method' => 'reset'])->public();
        // The tags are written once every listener that may still add one has run: after the session listener, the
        // last of the framework's to change the response, and before the profiler, so that its snapshot shows them.
        $services->set(ResponseTagsListener::class)
            ->args([new Reference(ResponseTags::class), $header, $separator])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse', 'after' => SessionListener::class, 'before' => ProfilerListener::class]);

        if ('varnish' === $config['purger']) {
            $services->set(VarnishPurger::class)->args([new Reference('http_client'), $config['varnish']['urls'], $config['varnish']['header']]);
            $services->alias(CacheTagPurger::class, VarnishPurger::class);
        }

        if ('cloudflare' === $config['purger']) {
            $services->set(CloudflarePurger::class)->args([new Reference('http_client'), $config['cloudflare']['zone'], $config['cloudflare']['token']]);
            $services->alias(CacheTagPurger::class, CloudflarePurger::class);
        }

        $services->set(Invalidator::class)
            ->args([new Reference(CacheTagPurger::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new TaggedIteratorArgument(self::RESOLVER_TAG)])
            ->public();
        $services->set(InvalidationFlushListener::class)
            ->args([new Reference(Invalidator::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->tag('kernel.event_listener', ['event' => 'kernel.terminate'])
            ->tag('kernel.event_listener', ['event' => 'console.terminate'])
            ->tag('kernel.event_listener', ['event' => self::WORKER_MESSAGE_HANDLED])
            ->tag('kernel.event_listener', ['event' => self::WORKER_MESSAGE_FAILED])
            ->tag('monolog.logger', ['channel' => 'http_cache']);

        if ($config['invalidate_entities']) {
            $services->set(InvalidateOnFlushListener::class)
                ->args([new Reference(Invalidator::class)])
                ->tag('doctrine.event_listener', ['event' => 'onFlush']);
        }

        if (class_exists(AsTwigFunction::class)) {
            $services->set(CacheTagExtension::class)
                ->args([new Reference(ResponseTags::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
