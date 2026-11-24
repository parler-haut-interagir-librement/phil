<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\DependencyInjection;

use LogicException;
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
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Infrastructure\Content\ContentFeedSource;
use Phil\FeedBundle\Infrastructure\Content\Contract\ContentEntries;
use Phil\FeedBundle\Infrastructure\Content\PublishedContentEntries;
use Phil\FeedBundle\Infrastructure\Http\FeedController;
use Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls;
use Phil\FeedBundle\Infrastructure\Twig\FeedExtension;
use Phil\FeedBundle\Infrastructure\Twig\FeedRuntime;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdateHandler;
use Phil\LocaleDimensions\Locale;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The services of the feeds: the sources, tagged, the writers of the formats served, the controller, the Twig
 * function, the WebSub announcements when a hub is configured, and a source per content feed.
 *
 * @phpstan-type ContentFeed array{kind: string, locale: string, title: string, home_page: string, description: ?string, tag_authority: ?string}
 * @phpstan-type FeedConfig array{limit: int, formats: list<string>, allow_localhost: bool, cache: array{max_age: int, shared_max_age: ?int}, websub: array{hubs: list<string>, timeout: float, max_duration: float, user_agent: string}, content: array<string, ContentFeed>}
 */
final class FeedServices
{
    public const string SOURCE_TAG = 'phil_feed.source';

    public const string WRITER_TAG = 'phil_feed.writer';

    public const string WEBSUB_CLIENT = 'phil_feed.websub_client';

    /** The most a hub's answer is read: its status is what counts. */
    private const int HUB_ANSWER_BYTES = Limits::MIN_BYTES;

    private const array WRITERS = ['atom' => AtomWriter::class, 'rss' => RssWriter::class, 'json' => JsonFeedWriter::class];

    /**
     * @param FeedConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(FeedSource::class)->addTag(self::SOURCE_TAG);

        // A format named twice registers its writer once: the second registration replaces the first.
        foreach ($config['formats'] as $format) {
            $container->register(self::WRITERS[$format], self::WRITERS[$format])->addTag(self::WRITER_TAG);
        }

        $container->register(FeedWriters::class, FeedWriters::class)->setArguments([new TaggedIteratorArgument(self::WRITER_TAG)]);
        $container->register(Feeds::class, Feeds::class)
            ->setArguments([new TaggedIteratorArgument(self::SOURCE_TAG), $config['limit']])
            ->setPublic(true);
        $container->register(RouterFeedUrls::class, RouterFeedUrls::class)->setArguments([new Reference('router'), $config['allow_localhost']]);
        $container->setAlias(FeedUrls::class, RouterFeedUrls::class);

        $hubs = array_map(static fn (string $hub): Definition => self::url($hub, $config['allow_localhost']), $config['websub']['hubs']);
        $container->register(FeedLinkBuilder::class, FeedLinkBuilder::class)->setArguments([new Reference(FeedUrls::class), $hubs]);
        $container->register(FeedController::class, FeedController::class)
            ->setArguments([new Reference(Feeds::class), new Reference(FeedWriters::class), new Reference(FeedLinkBuilder::class), $config['cache']['max_age'], $config['cache']['shared_max_age']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $container->register(FeedExtension::class, FeedExtension::class)->addTag('twig.extension');
        $container->register(FeedRuntime::class, FeedRuntime::class)
            ->setArguments([new Reference(Feeds::class), new Reference(FeedWriters::class), new Reference(FeedUrls::class)])
            ->addTag('twig.runtime');

        if ([] !== $hubs) {
            self::registerWebSub($container, $config['websub'], $hubs);
        }

        if ([] !== $config['content']) {
            self::registerContent($container, $config['content'], $config['allow_localhost']);
        }
    }

    /**
     * @param array{hubs: list<string>, timeout: float, max_duration: float, user_agent: string} $websub
     * @param list<Definition>                                                                   $hubs
     */
    private static function registerWebSub(ContainerBuilder $container, array $websub, array $hubs): void
    {
        $container->register(self::WEBSUB_CLIENT, SafeHttpClient::class)->setArguments([
            new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Definition(Limits::class, [$websub['timeout'], $websub['max_duration'], self::HUB_ANSWER_BYTES, 0, $websub['user_agent']]),
        ])->addTag('kernel.reset', ['method' => 'reset']);

        $container->register(AnnounceFeedUpdateHandler::class, AnnounceFeedUpdateHandler::class)
            ->setArguments([new Reference(Feeds::class), new Reference(FeedWriters::class), new Reference(FeedUrls::class), new Reference(self::WEBSUB_CLIENT), $hubs, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('messenger.message_handler', ['handles' => AnnounceFeedUpdate::class]);

        $container->register(AnnounceChangedFeeds::class, AnnounceChangedFeeds::class)
            ->setArguments([new Reference(Feeds::class), new Reference(MessageBusInterface::class)])
            ->addTag('kernel.event_listener', ['event' => PagePublished::class])
            ->addTag('kernel.event_listener', ['event' => PageRetired::class]);
    }

    /**
     * @param array<string, ContentFeed> $feeds
     */
    private static function registerContent(ContainerBuilder $container, array $feeds, bool $allowLocalhost): void
    {
        if (!class_exists(PublishedContents::class)) {
            throw new LogicException('The content feeds of phil_feed.content read phil/content-bundle: composer require phil/content-bundle.');
        }

        $container->register(PublishedContentEntries::class, PublishedContentEntries::class)
            ->setArguments([new Reference(PublishedContents::class), new Reference(ContentUrlGenerator::class)]);
        $container->setAlias(ContentEntries::class, PublishedContentEntries::class);

        foreach ($feeds as $name => $feed) {
            $locale = new Definition(Locale::class, [$feed['locale']])->setFactory([Locale::class, 'fromString']);
            $channel = new Definition(Channel::class, [
                $feed['title'],
                self::url($feed['home_page'], $allowLocalhost),
                $feed['description'],
                Locale::fromString($feed['locale'])->toLanguageTag(),
            ]);

            $container->register('phil_feed.content.'.$name, ContentFeedSource::class)
                ->setArguments([new Reference(ContentEntries::class), $name, $feed['kind'], $locale, $channel, $feed['tag_authority'], $allowLocalhost])
                ->addTag(self::SOURCE_TAG);
        }
    }

    private static function url(string $url, bool $allowLocalhost): Definition
    {
        return new Definition(Url::class, [$url, $allowLocalhost])->setFactory([Url::class, 'fromString']);
    }
}
