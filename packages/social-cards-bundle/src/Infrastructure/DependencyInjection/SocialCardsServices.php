<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\DependencyInjection;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\DirectoryCardStore;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Contract\CardStore;
use Phil\SocialCards\Preview\TagReader;
use Phil\SocialCardsBundle\Application\CardTemplates;
use Phil\SocialCardsBundle\Application\Contract\CardRequestStore;
use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Infrastructure\Console\CheckSocialTagsCommand;
use Phil\SocialCardsBundle\Infrastructure\Http\GuardedPageFetcher;
use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory;
use Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController;
use Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages;
use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCardHandler;
use Phil\SocialCardsBundle\Infrastructure\Messenger\MessengerCardQueue;
use Phil\SocialCardsBundle\Infrastructure\Store\DirectoryCardRequestStore;
use Phil\SocialCardsBundle\Infrastructure\Twig\Components\LinkPreviewComponent;
use Phil\SocialCardsBundle\Infrastructure\Twig\SocialMetaExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bundle's services: the page and its sources, the metadata and `social_meta()`; the cards, their store, their
 * route and, with Messenger, their queue; the link previews, their component and the check command; the integrations
 * with phil/media-bundle and phil/http-cache-tags-bundle when they are there.
 *
 * @phpstan-type Config array{site_name: ?string, locale: ?string, twitter_site: ?string, fediverse_creator: ?string, default_image: array<string, mixed>|string|null, cards: array{enabled: bool, directory: string, cache_max_age: int, async: bool, default_template: string, templates: array<string, array<string, mixed>>}, preview: array{enabled: bool, timeout: float, max_duration: float, max_bytes: int, max_redirects: int, cache_ttl: int, user_agent: string, limiter: ?string}}
 */
final class SocialCardsServices
{
    public const string SOURCE_TAG = 'phil_social_cards.source';

    /** The guarded client of the link previews, with the limits of `preview`. */
    public const string HTTP_CLIENT = 'phil_social_cards.safe_http_client';

    /** The service each optional service needs, by its own id. */
    public const array REQUIREMENTS = [
        MessengerCardQueue::class => 'messenger.default_bus',
        GenerateSocialCardHandler::class => 'messenger.default_bus',
        MediaShareImages::class => MediaExtension::class,
    ];

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(ShareMetadataSource::class)->addTag(self::SOURCE_TAG);
        $container->register(SharePage::class, SharePage::class)
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);

        $cards = $config['cards']['enabled'] && [] !== $config['cards']['templates'];

        if ($cards) {
            self::cards($container, $config['cards']);
        }

        if (class_exists(MediaExtension::class)) {
            $container->register(MediaShareImages::class, MediaShareImages::class)->setArguments([new Reference(MediaExtension::class)]);
        }

        $container->register(ShareMetadataFactory::class, ShareMetadataFactory::class)
            ->setArguments([
                new Reference(SharePage::class),
                new Reference('request_stack'),
                new Reference('router'),
                array_filter([
                    'site_name' => $config['site_name'],
                    'locale' => $config['locale'],
                    'twitter_site' => $config['twitter_site'],
                    'fediverse_creator' => $config['fediverse_creator'],
                    'image' => $config['default_image'],
                ], static fn (mixed $value): bool => null !== $value),
                new TaggedIteratorArgument(self::SOURCE_TAG),
                $cards ? new Reference(SocialCards::class) : null,
                new Reference(MediaShareImages::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(SocialMetaExtension::class, SocialMetaExtension::class)
            ->setArguments([new Reference(ShareMetadataFactory::class), new Reference(SharePage::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        if ($config['preview']['enabled']) {
            self::previews($container, $config['preview']);
        }
    }

    /**
     * @param array{enabled: bool, directory: string, cache_max_age: int, async: bool, default_template: string, templates: array<string, array<string, mixed>>} $config
     */
    private static function cards(ContainerBuilder $container, array $config): void
    {
        $container->register(CardTemplates::class, CardTemplates::class)->setArguments([$config['templates'], $config['default_template']]);
        $container->register(DirectoryCardStore::class, DirectoryCardStore::class)->setArguments([$config['directory']]);
        $container->setAlias(CardStore::class, DirectoryCardStore::class);
        $container->register(DirectoryCardRequestStore::class, DirectoryCardRequestStore::class)->setArguments([$config['directory']]);
        $container->setAlias(CardRequestStore::class, DirectoryCardRequestStore::class);
        $container->register(GdCardRenderer::class, GdCardRenderer::class);
        $container->register(CardGenerator::class, CardGenerator::class)->setArguments([new Reference(GdCardRenderer::class), new Reference(CardStore::class)]);

        if ($config['async'] && interface_exists(MessageBusInterface::class)) {
            $container->register(MessengerCardQueue::class, MessengerCardQueue::class)->setArguments([new Reference('messenger.default_bus')]);
            $container->register(GenerateSocialCardHandler::class, GenerateSocialCardHandler::class)
                ->setArguments([new Reference(SocialCards::class)])
                ->addTag('messenger.message_handler');
        }

        $container->register(SocialCards::class, SocialCards::class)
            ->setArguments([
                new Reference(CardTemplates::class),
                new Reference(CardGenerator::class),
                new Reference(CardRequestStore::class),
                new Reference(CardStore::class),
                new Reference(MessengerCardQueue::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(SocialCardController::class, SocialCardController::class)
            ->setArguments([new Reference(SocialCards::class), $config['cache_max_age'], new Reference(ResponseTags::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * @param array{enabled: bool, timeout: float, max_duration: float, max_bytes: int, max_redirects: int, cache_ttl: int, user_agent: string, limiter: ?string} $config
     */
    private static function previews(ContainerBuilder $container, array $config): void
    {
        $container->register(self::HTTP_CLIENT, SafeHttpClient::class)
            ->setArguments([
                new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Definition(Limits::class, [$config['timeout'], $config['max_duration'], $config['max_bytes'], $config['max_redirects'], $config['user_agent']]),
            ])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(GuardedPageFetcher::class, GuardedPageFetcher::class)
            ->setArguments([new Reference(self::HTTP_CLIENT)])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(PageFetcher::class, GuardedPageFetcher::class);
        $container->register(TagReader::class, TagReader::class)->setArguments([$config['max_bytes']]);
        $container->register(LinkPreviews::class, LinkPreviews::class)
            ->setArguments([new Reference(PageFetcher::class), new Reference(TagReader::class), new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['cache_ttl']])
            ->setPublic(true);

        $container->register(LinkPreviewController::class, LinkPreviewController::class)
            ->setArguments([new Reference(LinkPreviews::class), null === $config['limiter'] ? null : new Reference($config['limiter'])])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        if (class_exists(AsTwigComponent::class)) {
            $container->register(LinkPreviewComponent::class, LinkPreviewComponent::class)
                ->setArguments([new Reference(LinkPreviews::class)])
                ->setAutoconfigured(true);
        }

        if (class_exists(Command::class)) {
            $container->register(CheckSocialTagsCommand::class, CheckSocialTagsCommand::class)
                ->setArguments([new Reference('http_kernel'), new Reference('router.request_context'), new Reference(LinkPreviews::class), new Reference(TagReader::class)])
                ->addTag('console.command', ['command' => 'phil:social-cards:check', 'description' => 'Checks the Open Graph, X and fediverse tags of a page.']);
        }
    }
}
