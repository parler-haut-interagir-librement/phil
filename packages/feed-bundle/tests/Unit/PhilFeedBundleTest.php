<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit;

use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Application\FeedLinkBuilder;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\JsonFeedWriter;
use Phil\FeedBundle\Application\Writer\RssWriter;
use Phil\FeedBundle\Infrastructure\Content\ContentFeedSource;
use Phil\FeedBundle\Infrastructure\Content\Contract\ContentEntries;
use Phil\FeedBundle\Infrastructure\Content\PublishedContentEntries;
use Phil\FeedBundle\Infrastructure\DependencyInjection\FeedServices;
use Phil\FeedBundle\Infrastructure\Http\FeedController;
use Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls;
use Phil\FeedBundle\Infrastructure\Twig\FeedExtension;
use Phil\FeedBundle\Infrastructure\Twig\FeedRuntime;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdateHandler;
use Phil\FeedBundle\PhilFeedBundle;
use Phil\FeedBundle\Tests\Fixtures\StaticSource;
use Phil\LocaleDimensions\Locale;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\SafeHttp\Limits;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PhilFeedBundle::class)]
#[CoversClass(FeedServices::class)]
final class PhilFeedBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_feed', $this->extension()->getAlias());
        self::assertSame([20], [$container->getDefinition(Feeds::class)->getArgument(1)]);
        self::assertTrue($container->getDefinition(Feeds::class)->isPublic());

        foreach ([AtomWriter::class, RssWriter::class, JsonFeedWriter::class] as $writer) {
            self::assertTrue($container->getDefinition($writer)->hasTag(FeedServices::WRITER_TAG), $writer);
        }

        $controller = $container->getDefinition(FeedController::class);
        self::assertSame([900, null], [$controller->getArgument(3), $controller->getArgument(4)]);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertFalse($container->hasDefinition(AnnounceChangedFeeds::class));
        self::assertFalse($container->hasDefinition(AnnounceFeedUpdateHandler::class));
        self::assertFalse($container->hasDefinition(PublishedContentEntries::class));
        self::assertFalse($container->getDefinition(RouterFeedUrls::class)->getArgument(1));
        self::assertSame([], $container->getDefinition(FeedLinkBuilder::class)->getArgument(1));
        self::assertTrue($container->getDefinition(FeedExtension::class)->hasTag('twig.extension'));
        self::assertTrue($container->getDefinition(FeedRuntime::class)->hasTag('twig.runtime'));
    }

    public function testTheOptions(): void
    {
        $container = $this->load([['limit' => 500, 'formats' => ['json', 'atom', 'json'], 'allow_localhost' => true, 'cache' => ['max_age' => 0, 'shared_max_age' => 60]]]);

        self::assertSame(500, $container->getDefinition(Feeds::class)->getArgument(1));
        self::assertFalse($container->hasDefinition(RssWriter::class));
        self::assertTrue($container->hasDefinition(JsonFeedWriter::class));
        self::assertTrue($container->hasDefinition(AtomWriter::class));
        self::assertSame([0, 60], [$container->getDefinition(FeedController::class)->getArgument(3), $container->getDefinition(FeedController::class)->getArgument(4)]);
        self::assertTrue($container->getDefinition(RouterFeedUrls::class)->getArgument(1));
    }

    public function testAHubTurnsTheAnnouncementsOn(): void
    {
        $container = $this->load([['allow_localhost' => true, 'websub' => ['hubs' => ['http://hub.localhost/'], 'timeout' => 2.5, 'max_duration' => 4.0, 'user_agent' => 'Example/1.0']]]);

        $listener = $container->getDefinition(AnnounceChangedFeeds::class);
        self::assertSame([['event' => PagePublished::class], ['event' => PageRetired::class]], $listener->getTag('kernel.event_listener'));
        self::assertSame([['handles' => AnnounceFeedUpdate::class]], $container->getDefinition(AnnounceFeedUpdateHandler::class)->getTag('messenger.message_handler'));

        $client = $container->getDefinition(FeedServices::WEBSUB_CLIENT);
        self::assertTrue($client->hasTag('kernel.reset'));
        $limits = $client->getArgument(1);
        self::assertInstanceOf(Definition::class, $limits);
        self::assertSame([2.5, 4.0, Limits::MIN_BYTES, 0, 'Example/1.0'], $limits->getArguments());

        $hubs = $container->getDefinition(FeedLinkBuilder::class)->getArgument(1);
        self::assertIsArray($hubs);
        self::assertCount(1, $hubs);
        self::assertInstanceOf(Definition::class, $hubs[0]);
        self::assertSame(['http://hub.localhost/', true], $hubs[0]->getArguments());
        self::assertSame($hubs, $container->getDefinition(AnnounceFeedUpdateHandler::class)->getArgument(4));
    }

    public function testAContentFeedIsASource(): void
    {
        $container = $this->load([['content' => ['nouvelles-fr' => ['kind' => 'news', 'locale' => 'fr_CA', 'title' => 'Nouvelles', 'home_page' => 'https://example.org/fr', 'description' => 'Tout', 'tag_authority' => 'example.org,2026']]]]);

        self::assertSame(PublishedContentEntries::class, (string) $container->getAlias(ContentEntries::class));
        $source = $container->getDefinition('phil_feed.content.nouvelles-fr');
        self::assertSame(ContentFeedSource::class, $source->getClass());
        self::assertTrue($source->hasTag(FeedServices::SOURCE_TAG));
        self::assertSame(['nouvelles-fr', 'news'], [$source->getArgument(1), $source->getArgument(2)]);
        self::assertSame(['example.org,2026', false], [$source->getArgument(5), $source->getArgument(6)]);
        $channel = $source->getArgument(4);
        self::assertInstanceOf(Definition::class, $channel);
        self::assertSame(['Nouvelles', 'Tout', 'fr-CA'], [$channel->getArgument(0), $channel->getArgument(2), $channel->getArgument(3)]);
        $locale = $source->getArgument(3);
        self::assertInstanceOf(Definition::class, $locale);
        self::assertSame(['fr_CA'], $locale->getArguments());
    }

    public function testTheServicesReadEachOther(): void
    {
        $container = $this->load([['allow_localhost' => true, 'websub' => ['hubs' => ['http://hub.localhost/']], 'content' => ['news' => self::content()]]]);

        self::assertEquals([new TaggedIteratorArgument(FeedServices::WRITER_TAG)], $container->getDefinition(FeedWriters::class)->getArguments());
        self::assertSame(RouterFeedUrls::class, (string) $container->getAlias(FeedUrls::class));
        self::assertEquals([new Reference(Feeds::class), new Reference(FeedWriters::class), new Reference(FeedUrls::class)], $container->getDefinition(FeedRuntime::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(FeedServices::WEBSUB_CLIENT)->getTag('kernel.reset'));
        self::assertEquals([new Reference(Feeds::class), new Reference(MessageBusInterface::class)], $container->getDefinition(AnnounceChangedFeeds::class)->getArguments());
        self::assertEquals([new Reference(PublishedContents::class), new Reference(ContentUrlGenerator::class)], $container->getDefinition(PublishedContentEntries::class)->getArguments());

        $source = $container->getDefinition('phil_feed.content.news');
        $locale = $source->getArgument(3);
        self::assertInstanceOf(Definition::class, $locale);
        self::assertSame([Locale::class, 'fromString'], $locale->getFactory());
        $channel = $source->getArgument(4);
        self::assertInstanceOf(Definition::class, $channel);
        $homePage = $channel->getArgument(1);
        self::assertInstanceOf(Definition::class, $homePage);
        self::assertSame([Url::class, 'fromString'], $homePage->getFactory());
        self::assertSame(['https://example.org/', true], $homePage->getArguments());
    }

    public function testTheSmallestPageAndSharedAge(): void
    {
        $container = $this->load([['limit' => 1, 'cache' => ['shared_max_age' => 0]]]);

        self::assertSame(1, $container->getDefinition(Feeds::class)->getArgument(1));
        self::assertSame(0, $container->getDefinition(FeedController::class)->getArgument(4));
    }

    public function testAWrongAddressKeepsItsCause(): void
    {
        try {
            $this->load([['websub' => ['hubs' => ['hub']]]]);
            self::fail('The hub is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertInstanceOf(InvalidUrl::class, $invalidConfigurationException->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'limit 0' => [['limit' => 0], 'phil_feed.limit'];
        yield 'limit 501' => [['limit' => 501], 'phil_feed.limit'];
        yield 'no format' => [['formats' => []], 'phil_feed.formats'];
        yield 'unknown format' => [['formats' => ['xml']], 'phil_feed.formats'];
        yield 'negative max age' => [['cache' => ['max_age' => -1]], 'phil_feed.cache.max_age'];
        yield 'negative shared max age' => [['cache' => ['shared_max_age' => -1]], 'phil_feed.cache.shared_max_age'];
        yield 'hub on localhost' => [['websub' => ['hubs' => ['http://localhost/']]], 'phil_feed.websub.hubs'];
        yield 'hub not an address' => [['websub' => ['hubs' => ['hub']]], 'phil_feed.websub.hubs'];
        yield 'long timeout' => [['websub' => ['timeout' => 61.0]], 'phil_feed.websub.timeout'];
        yield 'long duration' => [['websub' => ['max_duration' => 121.0]], 'phil_feed.websub.max_duration'];
        yield 'empty user agent' => [['websub' => ['user_agent' => '']], 'phil_feed.websub.user_agent'];
        yield 'feed name' => [['content' => ['News' => self::content()]], 'The name of a feed is 1 to 64 lowercase letters'];
        yield 'kind' => [['content' => ['news' => ['kind' => 'News'] + self::content()]], 'is not a resource key'];
        yield 'locale' => [['content' => ['news' => ['locale' => 'fr-CA'] + self::content()]], 'is not a locale such as fr or fr_CA'];
        yield 'home page' => [['content' => ['news' => ['home_page' => '/fr'] + self::content()]], 'phil_feed.content.news.home_page'];
        yield 'tag authority' => [['content' => ['news' => ['tag_authority' => 'example.org'] + self::content()]], 'is not a tag authority'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testAnInvalidConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    public function testTheLocalesAndKindsOfContent(): void
    {
        foreach (['fr', 'fr_CA', 'ast'] as $locale) {
            self::assertTrue($this->load([['content' => ['news' => ['locale' => $locale] + self::content()]]])->hasDefinition('phil_feed.content.news'), $locale);
        }

        foreach (['n', 'news_2026', 'a'.str_repeat('b', 63)] as $kind) {
            self::assertTrue($this->load([['content' => ['news' => ['kind' => $kind] + self::content()]]])->hasDefinition('phil_feed.content.news'), $kind);
        }

        foreach ([['locale' => 'fr_ca'], ['locale' => 'es_419'], ['locale' => 'f'], ['locale' => "fr\n"], ['kind' => '1news'], ['kind' => 'a'.str_repeat('b', 64)], ['kind' => 1]] as $wrong) {
            try {
                $this->load([['content' => ['news' => $wrong + self::content()]]]);
                self::fail(json_encode($wrong, \JSON_THROW_ON_ERROR).' is refused');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheSourcesOfTheApplicationAreTagged(): void
    {
        $container = $this->load([]);
        $container->register('app.news', StaticSource::class)->setAutoconfigured(true)->setArguments(['news']);

        $instanceof = $container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(FeedSource::class, $instanceof);
        self::assertTrue($instanceof[FeedSource::class]->hasTag(FeedServices::SOURCE_TAG));
    }

    /**
     * @return array{kind: string, locale: string, title: string, home_page: string}
     */
    private static function content(): array
    {
        return ['kind' => 'news', 'locale' => 'fr', 'title' => 'News', 'home_page' => 'https://example.org/'];
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
        $extension = new PhilFeedBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
