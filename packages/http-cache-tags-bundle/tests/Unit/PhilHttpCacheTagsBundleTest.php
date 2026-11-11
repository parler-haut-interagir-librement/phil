<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Unit;

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
use Phil\HttpCacheTagsBundle\PhilHttpCacheTagsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[CoversClass(PhilHttpCacheTagsBundle::class)]
final class PhilHttpCacheTagsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_http_cache_tags', $this->extension()->getAlias());
        self::assertSame(['service_container', ResponseTags::class, ResponseTagsListener::class, Invalidator::class, InvalidationFlushListener::class, InvalidateOnFlushListener::class, CacheTagExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([['method' => 'reset']], $container->getDefinition(ResponseTags::class)->getTag('kernel.reset'));
        self::assertTrue($container->getDefinition(ResponseTags::class)->isPublic());
        self::assertEquals([new Reference(ResponseTags::class), 'xkey', ' '], $container->getDefinition(ResponseTagsListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.controller', 'method' => 'onController'], ['event' => 'kernel.response', 'method' => 'onResponse', 'after' => SessionListener::class, 'before' => ProfilerListener::class]], $container->getDefinition(ResponseTagsListener::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference(CacheTagPurger::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new TaggedIteratorArgument('phil_http_cache_tags.resolver')], $container->getDefinition(Invalidator::class)->getArguments());
        self::assertTrue($container->getDefinition(Invalidator::class)->isPublic());
        self::assertEquals([new Reference(Invalidator::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(InvalidationFlushListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.terminate'], ['event' => 'console.terminate'], ['event' => PhilHttpCacheTagsBundle::WORKER_MESSAGE_HANDLED], ['event' => PhilHttpCacheTagsBundle::WORKER_MESSAGE_FAILED]], $container->getDefinition(InvalidationFlushListener::class)->getTag('kernel.event_listener'));
        self::assertSame([WorkerMessageHandledEvent::class, WorkerMessageFailedEvent::class], [PhilHttpCacheTagsBundle::WORKER_MESSAGE_HANDLED, PhilHttpCacheTagsBundle::WORKER_MESSAGE_FAILED]);
        self::assertSame([['channel' => 'http_cache']], $container->getDefinition(InvalidationFlushListener::class)->getTag('monolog.logger'));
        self::assertEquals([new Reference(Invalidator::class)], $container->getDefinition(InvalidateOnFlushListener::class)->getArguments());
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(InvalidateOnFlushListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(ResponseTags::class)], $container->getDefinition(CacheTagExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(CacheTagExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(CacheTagExtension::class)->hasTag('twig.runtime'));
        self::assertFalse($container->hasAlias(CacheTagPurger::class));
        self::assertTrue($container->getAutoconfiguredInstanceof()[CacheTagResolver::class]->hasTag(PhilHttpCacheTagsBundle::RESOLVER_TAG));
        self::assertSame('phil_http_cache_tags.resolver', PhilHttpCacheTagsBundle::RESOLVER_TAG);
        self::assertFalse($this->load([['invalidate_entities' => false]])->hasDefinition(InvalidateOnFlushListener::class));
    }

    public function testEachHeaderAndPurger(): void
    {
        self::assertSame(['Cache-Tag', ','], \array_slice($this->load([['header' => 'cache-tag']])->getDefinition(ResponseTagsListener::class)->getArguments(), 1));
        self::assertSame(['Surrogate-Key', ' '], \array_slice($this->load([['header' => 'surrogate-key']])->getDefinition(ResponseTagsListener::class)->getArguments(), 1));

        $varnish = $this->load([['purger' => 'varnish', 'varnish' => ['urls' => ['http://varnish'], 'header' => 'xkey-softpurge']]]);
        self::assertEquals([new Reference('http_client'), ['http://varnish'], 'xkey-softpurge'], $varnish->getDefinition(VarnishPurger::class)->getArguments());
        self::assertSame(VarnishPurger::class, (string) $varnish->getAlias(CacheTagPurger::class));
        self::assertFalse($varnish->hasDefinition(CloudflarePurger::class));
        self::assertSame('xkey-purge', $this->load([['purger' => 'varnish', 'varnish' => ['urls' => ['http://varnish']]]])->getDefinition(VarnishPurger::class)->getArgument(2));

        $cloudflare = $this->load([['purger' => 'cloudflare', 'cloudflare' => ['zone' => 'z', 'token' => 't']]]);
        self::assertEquals([new Reference('http_client'), 'z', 't'], $cloudflare->getDefinition(CloudflarePurger::class)->getArguments());
        self::assertSame(CloudflarePurger::class, (string) $cloudflare->getAlias(CacheTagPurger::class));
        self::assertFalse($cloudflare->hasDefinition(VarnishPurger::class));
    }

    public function testAPurgerNeedsItsSettings(): void
    {
        foreach ([
            [['purger' => 'varnish'], 'Purging Varnish needs its URLs: phil_http_cache_tags.varnish.urls.'],
            [['purger' => 'cloudflare', 'cloudflare' => ['zone' => 'z']], 'Purging Cloudflare needs its zone and an API token: phil_http_cache_tags.cloudflare.'],
            [['purger' => 'cloudflare', 'cloudflare' => ['token' => 't']], 'Purging Cloudflare needs its zone and an API token: phil_http_cache_tags.cloudflare.'],
            [['header' => 'etag'], 'The value "etag" is not allowed'],
        ] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        self::assertFalse($this->load([['purger' => 'none', 'varnish' => ['urls' => []], 'cloudflare' => ['zone' => null]]])->hasAlias(CacheTagPurger::class));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilHttpCacheTagsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
