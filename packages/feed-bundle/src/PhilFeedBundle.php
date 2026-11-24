<?php

declare(strict_types=1);

namespace Phil\FeedBundle;

use Override;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\FeedBundle\Infrastructure\Content\ContentFeedSource;
use Phil\FeedBundle\Infrastructure\DependencyInjection\FeedServices;
use Phil\SafeHttp\Limits;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Atom, RSS 2.0 and JSON Feed 1.1 of any list of entries: news, the pages of a kind of content, the events of a
 * calendar. Each `FeedSource` of the application is served at `/feeds/{name}.{format}`, linked from a page by
 * `phil_feed_links()`, and announced to WebSub hubs when a page it lists is published.
 *
 * ```yaml
 * phil_feed:
 *     websub:
 *         hubs: ['https://hub.example.org/']
 * ```
 *
 * @phpstan-import-type FeedConfig from FeedServices
 */
final class PhilFeedBundle extends AbstractBundle
{
    private const string KIND = '/^[a-z][a-z0-9_]{0,63}$/D';

    private const string LOCALE = '/^[a-z]{2,3}(?:_[A-Z]{2})?$/D';

    #[Override]
    protected string $extensionAlias = 'phil_feed';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('limit')
                    ->info('The entries of a page of a feed, the most recent first.')
                    ->defaultValue(20)
                    ->min(1)
                    ->max(FeedQuery::MAX_LIMIT)
                ->end()
                ->arrayNode('formats')
                    ->info('The formats the feeds are served in.')
                    ->enumPrototype()->values(array_map(static fn (FeedFormat $format): string => $format->value, FeedFormat::cases()))->end()
                    ->defaultValue(['atom', 'rss', 'json'])
                    ->requiresAtLeastOneElement()
                ->end()
                ->booleanNode('allow_localhost')
                    ->info('Whether the feeds, their pages and the hubs may be on localhost or under .localhost: true in development and tests only.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_age')
                            ->info('The seconds a feed may be kept by browsers and shared caches; 0 keeps it in none.')
                            ->defaultValue(900)
                            ->min(0)
                        ->end()
                        ->integerNode('shared_max_age')
                            ->info('The seconds a feed may be kept by shared caches, a reverse proxy or a CDN; null follows max_age.')
                            ->defaultNull()
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('websub')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('hubs')
                            ->info('The WebSub hubs told when a feed changes; none turns the announcements off.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->floatNode('timeout')
                            ->info('The seconds a hub may stay silent.')
                            ->defaultValue(5.0)
                            ->min(0.1)
                            ->max(Limits::MAX_TIMEOUT)
                        ->end()
                        ->floatNode('max_duration')
                            ->info('The seconds an announcement to a hub may last.')
                            ->defaultValue(10.0)
                            ->min(0.1)
                            ->max(Limits::MAX_DURATION)
                        ->end()
                        ->scalarNode('user_agent')
                            ->info('The User-Agent of the announcements.')
                            ->defaultValue('PhilFeed/0.1')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('content')
                    ->info('Feeds of phil/content-bundle, by name: the published contents of a kind in a locale.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static fn (array $feeds): bool => [] !== array_filter(array_keys($feeds), static fn (int|string $name): bool => 1 !== preg_match('/^'.Feeds::NAME_PATTERN.'$/D', (string) $name)))
                        ->thenInvalid('The name of a feed is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit: %s.')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('kind')
                                ->info('The resource key of the kind of content.')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(static fn (mixed $kind): bool => !\is_string($kind) || 1 !== preg_match(self::KIND, $kind))
                                    ->thenInvalid('%s is not a resource key: a lowercase letter, then at most 63 lowercase letters, digits or underscores.')
                                ->end()
                            ->end()
                            ->scalarNode('locale')
                                ->info('The locale whose contents the feed lists, as fr or fr_CA.')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(static fn (mixed $locale): bool => !\is_string($locale) || 1 !== preg_match(self::LOCALE, $locale))
                                    ->thenInvalid('%s is not a locale such as fr or fr_CA.')
                                ->end()
                            ->end()
                            ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('home_page')
                                ->info('The absolute address of the page of the site the feed follows.')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('description')->defaultNull()->end()
                            ->scalarNode('tag_authority')
                                ->info('A domain and a date, as example.org,2026: the entries are then named tag:example.org,2026:{reference}, which survives a change of slug.')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (mixed $authority): bool => null !== $authority && (!\is_string($authority) || 1 !== preg_match(ContentFeedSource::TAG_AUTHORITY, $authority)))
                                    ->thenInvalid('%s is not a tag authority: a domain, a comma and a date, as example.org,2026 or example.org,2026-11-21.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var FeedConfig $config */
        $this->checkUrls($config);
        FeedServices::register($builder, $config);
    }

    /**
     * @param FeedConfig $config
     */
    private function checkUrls(array $config): void
    {
        foreach ($config['websub']['hubs'] as $hub) {
            $this->checkUrl('phil_feed.websub.hubs', $hub, $config['allow_localhost']);
        }

        foreach ($config['content'] as $name => $feed) {
            $this->checkUrl('phil_feed.content.'.$name.'.home_page', $feed['home_page'], $config['allow_localhost']);
        }
    }

    private function checkUrl(string $path, string $url, bool $allowLocalhost): void
    {
        try {
            Url::fromString($url, $allowLocalhost);
        } catch (InvalidUrl $invalidUrl) {
            throw new InvalidConfigurationException(\sprintf('Invalid configuration for path "%s": %s', $path, $invalidUrl->getMessage()), $invalidUrl->getCode(), previous: $invalidUrl);
        }
    }
}
